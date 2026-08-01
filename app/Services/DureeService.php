<?php

namespace App\Services;

class DureeService
{
    /**
     * Convertit une valeur + unité en minutes.
     *
     * Exemples :
     *   toMinutes(2,  'heures')  → 120
     *   toMinutes(7,  'jours')   → 10080
     *   toMinutes(45, 'minutes') → 45
     *   toMinutes(1,  'semaines')→ 10080
     *   toMinutes(1,  'mois')    → 43200  (approximation 30j)
     */
    public static function toMinutes(int $valeur, string $unite): int
    {
        return $valeur * match ($unite) {
            'minutes'  => 1,
            'heures'   => 60,
            'jours'    => 60 * 24,
            'semaines' => 60 * 24 * 7,
            'mois'     => 60 * 24 * 30,
            default    => 60,   // fallback : heures
        };
    }

    /**
     * Retourne un libellé lisible.
     *
     * Exemple : label(2, 'heures') → "2 heures"
     */
    public static function label(int $valeur, string $unite): string
    {
        return "{$valeur} {$unite}";
    }
}
