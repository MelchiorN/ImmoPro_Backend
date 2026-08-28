<?php

namespace App\Console\Commands;

use App\Models\Bien;
use App\Models\ConfigPublication;
use App\Models\User;
use App\Services\DureeService;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    protected $signature   = 'sla:check {--dry-run : Simulation sans envoi ni écriture}';
    protected $description = 'Vérifie les SLA des dossiers et alerte les admins si dépassement';

    public function handle(NotificationService $notif): int
    {
        $dryRun  = $this->option('dry-run');
        $config  = ConfigPublication::instance();
        $admins  = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('Aucun admin trouvé — arrêt.');
            return self::SUCCESS;
        }

        // ── Résoudre les seuils SLA ───────────────────────────────────────────
        $sla1Minutes = DureeService::toMinutes(
            (int)    ($config->sla1_valeur ?? 2),
            (string) ($config->sla1_unite  ?? 'heures')
        );
        $sla2Minutes = DureeService::toMinutes(
            (int)    ($config->sla2_valeur ?? 7),
            (string) ($config->sla2_unite  ?? 'jours')
        );

        $alertes1 = 0;
        $alertes2 = 0;

        // ── SLA 1 : dossiers en_attente sans agent depuis > sla1 ─────────────
        Bien::where('statut', 'en_attente')
            ->whereNull('agent_id')
            ->whereNull('sla1_alerted_at')
            ->get()
            ->each(function (Bien $bien) use ($sla1Minutes, $admins, $notif, $dryRun, &$alertes1) {
                $ref = $bien->submitted_at ?? $bien->created_at;
                if (! $ref || now()->diffInMinutes(Carbon::parse($ref), true) <= $sla1Minutes) {
                    return; // pas encore dépassé
                }

                $duree = $this->formatDuree($ref);
                $msg   = "SLA 1 dépassé : le dossier « {$bien->titre} » est en attente depuis {$duree} sans agent assigné.";

                $this->line("[SLA1] {$bien->titre} — {$duree}");

                if (! $dryRun) {
                    foreach ($admins as $admin) {
                        $html = EmailTemplateService::generic(
                            titre:   'Alerte SLA — Dossier sans agent',
                            intro:   $msg,
                            rows:    [
                                ['icon' => 'home',   'label' => 'Bien',       'value' => $bien->titre],
                                ['icon' => 'pin',    'label' => 'Adresse',    'value' => $bien->adresse ?? '—'],
                                ['icon' => 'clock',  'label' => 'En attente', 'value' => $duree],
                            ],
                            outro: 'Assignez un agent manuellement depuis le tableau de bord.'
                        );
                        $notif->notify($admin, 'sla1_alerte', 'Alerte SLA — Dossier sans agent', $msg,
                            ['bien_id' => $bien->id], "ImmoPro — SLA dépassé : {$bien->titre}", $html);
                    }
                    $bien->update(['sla1_alerted_at' => now()]);
                }
                $alertes1++;
            });

        // ── SLA 2 : dossiers en_cours sans clôture depuis > sla2 ─────────────
        Bien::where('statut', 'en_cours')
            ->whereNotNull('claimed_at')
            ->whereNull('sla2_alerted_at')
            ->get()
            ->each(function (Bien $bien) use ($sla2Minutes, $admins, $notif, $dryRun, &$alertes2) {
                $ref = $bien->claimed_at;
                if (now()->diffInMinutes(Carbon::parse($ref), true) <= $sla2Minutes) {
                    return;
                }

                $duree    = $this->formatDuree($ref);
                $nomAgent = $bien->agent
                    ? trim("{$bien->agent->first_name} {$bien->agent->last_name}")
                    : 'Inconnu';

                $msg = "SLA 2 dépassé : le dossier « {$bien->titre} » est en cours depuis {$duree} sans clôture (agent : {$nomAgent}).";

                $this->line("[SLA2] {$bien->titre} — {$duree} — agent : {$nomAgent}");

                if (! $dryRun) {
                    foreach ($admins as $admin) {
                        $html = EmailTemplateService::generic(
                            titre:   'Alerte SLA — Dossier sans progression',
                            intro:   $msg,
                            rows:    [
                                ['icon' => 'home',  'label' => 'Bien',     'value' => $bien->titre],
                                ['icon' => 'user',  'label' => 'Agent',    'value' => $nomAgent],
                                ['icon' => 'clock', 'label' => 'En cours', 'value' => $duree],
                            ],
                            outro: 'Vérifiez l\'avancement du dossier avec l\'agent concerné.'
                        );
                        $notif->notify($admin, 'sla2_alerte', 'Alerte SLA — Dossier sans progression', $msg,
                            ['bien_id' => $bien->id, 'agent' => $nomAgent],
                            "ImmoPro — SLA 2 dépassé : {$bien->titre}", $html);
                    }
                    $bien->update(['sla2_alerted_at' => now()]);
                }
                $alertes2++;
            });

        $this->info("SLA check terminé" . ($dryRun ? ' [dry-run]' : '') . " — SLA1: {$alertes1} alerte(s), SLA2: {$alertes2} alerte(s).");
        return self::SUCCESS;
    }

    private function formatDuree($date): string
    {
        $minutes = now()->diffInMinutes(Carbon::parse($date), true);
        if ($minutes < 60)   return $minutes . ' min';
        if ($minutes < 1440) return round($minutes / 60) . 'h';
        return round($minutes / 1440) . ' jour(s)';
    }
}
