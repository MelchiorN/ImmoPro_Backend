<?php

namespace App\Console\Commands;

use App\Models\RappelVisiteConfig;
use App\Models\User;
use App\Models\Visite;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnvoyerRappelsVisitesCommand extends Command
{
    protected $signature = 'visites:rappels
        {--dry-run : Simulation sans envoi ni écriture}
        {--visite-id= : Tester sur une visite spécifique}';

    protected $description = 'Envoie les rappels automatiques de visites (agent + propriétaire)';

    public function handle(NotificationService $notif): int
    {
        $dryRun   = $this->option('dry-run');
        $visiteId = $this->option('visite-id');
        $rappels  = RappelVisiteConfig::where('actif', true)->orderBy('ordre')->get();

        if ($rappels->isEmpty()) {
            $this->warn('Aucun rappel actif configuré.');
            return self::SUCCESS;
        }

        $totalVisites = 0;
        $totalEnvoyes = 0;

        foreach ($rappels as $rappel) {
            // ── Trouver les visites concernées ────────────────────────────────
            $query = Visite::with(['bien.proprietaire', 'agent'])
                ->where('statut', 'confirmee')
                ->whereNotNull('date_visite');

            if ($visiteId) {
                $query->where('id', $visiteId);
            }

            // Filtre fenêtre temporelle
            if ($rappel->est_jour_j) {
                $heureEnvoi = Carbon::today()->setTimeFromTimeString($rappel->heure_jour_j ?? '08:00');
                if (now()->lt($heureEnvoi->copy()->subMinutes(15)) || now()->gt($heureEnvoi->copy()->addMinutes(15))) {
                    continue; // hors fenêtre d'envoi jour J
                }
                $query->whereDate('date_visite', today());
            } else {
                $cibleMinutes = $rappel->toMinutes();
                $debut = now()->addMinutes($cibleMinutes - 15);
                $fin   = now()->addMinutes($cibleMinutes + 15);
                $query->whereBetween('date_visite', [$debut, $fin]);
            }

            $visites = $query->get()->filter(function (Visite $v) use ($rappel) {
                $envoyes = $v->rappels_envoyes ?? [];
                return ! in_array($rappel->id, $envoyes);
            });

            foreach ($visites as $visite) {
                $bien      = $visite->bien;
                $agent     = $visite->agent;
                $proprio   = $bien?->proprietaire;
                $dateLabel = Carbon::parse($visite->date_visite)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');
                $titreBien = $bien?->titre ?? 'Votre bien';
                $label     = $rappel->label();

                $sujet  = $rappel->est_jour_j
                    ? "📍 Visite aujourd'hui — {$titreBien}"
                    : "⏰ Rappel visite ({$label}) — {$titreBien}";

                $intro = $rappel->est_jour_j
                    ? "Votre visite pour « {$titreBien} » a lieu aujourd'hui à " . Carbon::parse($visite->date_visite)->format('H\hi') . "."
                    : "Rappel : votre visite pour « {$titreBien} » est prévue le {$dateLabel}.";

                $rows = [
                    ['icon' => '🏠', 'label' => 'Bien',         'value' => $titreBien],
                    ['icon' => '📅', 'label' => 'Date et heure','value' => $dateLabel],
                ];

                $html = EmailTemplateService::generic(
                    titre:   $sujet,
                    intro:   $intro,
                    rows:    $rows,
                    outro:   'Merci de confirmer votre disponibilité si ce n\'est pas encore fait.'
                );

                $this->line("[Rappel {$label}] Visite {$visite->id} — {$titreBien} — {$dateLabel}");

                if (! $dryRun) {
                    // Notifier l'agent
                    if ($agent) {
                        $notif->notify($agent, 'rappel_visite', $sujet, $intro,
                            ['visite_id' => $visite->id, 'bien_id' => $bien?->id],
                            $sujet, $html);
                    }

                    // Notifier le propriétaire
                    if ($proprio) {
                        $notif->notify($proprio, 'rappel_visite', $sujet, $intro,
                            ['visite_id' => $visite->id, 'bien_id' => $bien?->id],
                            $sujet, $html);
                    }

                    // Marquer comme envoyé
                    $envoyes   = $visite->rappels_envoyes ?? [];
                    $envoyes[] = $rappel->id;
                    $visite->update(['rappels_envoyes' => array_unique($envoyes)]);
                }

                $totalEnvoyes++;
            }

            $totalVisites += $visites->count();
        }

        $this->info("Rappels terminés" . ($dryRun ? ' [dry-run]' : '') . " — {$totalVisites} visite(s) traitée(s), {$totalEnvoyes} rappel(s) envoyé(s).");
        return self::SUCCESS;
    }
}
