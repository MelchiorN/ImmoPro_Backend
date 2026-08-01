<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigPublication extends Model
{
    protected $table = 'config_publication';

    protected $fillable = [
        'essais_gratuits_defaut',
        'frais_etude_actifs',
        'sla1_valeur',
        'sla1_unite',
        'sla2_valeur',
        'sla2_unite',
        'visite_duree_valeur',
        'visite_duree_unite',
        'visite_delai_min_valeur',
        'visite_delai_min_unite',
    ];

    /**
     * Vérifie si les frais d'étude sont activés globalement.
     */
    public function fraisEtudeActifs(): bool
    {
        return (bool) $this->frais_etude_actifs;
    }

    protected function casts(): array
    {
        return [
            'frais_etude_actifs'      => 'boolean',
            'sla1_valeur'             => 'integer',
            'sla2_valeur'             => 'integer',
            'visite_duree_valeur'     => 'integer',
            'visite_delai_min_valeur' => 'integer',
        ];
    }

    /**
     * Retourne toujours la ligne unique de configuration.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'essais_gratuits_defaut' => 1,
                'frais_etude_actifs'     => false,
            ]
        );
    }
}
