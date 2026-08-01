<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\ConfigPublication;
use App\Models\Visite;
use Carbon\Carbon;

class CalendrierBienService
{
    /**
     * Vérifie que le bien est dans la bonne phase pour le type de visite.
     *  - 'verification' → statut ∈ {en_attente, en_cours}
     *  - 'client'       → statut = publie
     */
    public function phaseAutorisee(Bien $bien, string $typeVisite): bool
    {
        return match ($typeVisite) {
            'verification' => in_array($bien->statut, ['en_attente', 'en_cours']),
            'client'       => $bien->statut === 'publie',
            default        => false,
        };
    }

    /**
     * Vérifie qu'un créneau est disponible (pas de collision avec les visites
     * existantes du bien + respect du délai minimum entre visites).
     *
     * @param string $bienId    UUID du bien
     * @param Carbon $debut     Début souhaité
     * @param int    $dureeMins Durée en minutes
     */
    public function creneauDisponible(string $bienId, Carbon $debut, int $dureeMins): bool
    {
        $fin          = $debut->copy()->addMinutes($dureeMins);
        $delaiMin     = $this->delaiMinMinutes();

        // Fenêtre élargie avec le délai minimum de chaque côté
        $debutElargi  = $debut->copy()->subMinutes($delaiMin);
        $finElargie   = $fin->copy()->addMinutes($delaiMin);

        // Chercher une visite confirmée qui chevauche cette fenêtre
        $collision = Visite::where('bien_id', $bienId)
            ->whereIn('statut', ['proposee', 'confirmee'])
            ->where(function ($q) use ($debutElargi, $finElargie, $dureeMins) {
                // La visite existante chevauche [debutElargi, finElargie]
                $q->where('date_visite', '<', $finElargie)
                  ->whereRaw(
                      'DATE_ADD(date_visite, INTERVAL ? MINUTE) > ?',
                      [$dureeMins, $debutElargi]
                  );
            })
            ->exists();

        return ! $collision;
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function delaiMinMinutes(): int
    {
        try {
            $config = ConfigPublication::instance();
            $valeur = $config->visite_delai_min_valeur ?? 12;
            $unite  = $config->visite_delai_min_unite  ?? 'heures';
            return DureeService::toMinutes((int) $valeur, (string) $unite);
        } catch (\Throwable) {
            return 720; // 12 heures par défaut
        }
    }
}
