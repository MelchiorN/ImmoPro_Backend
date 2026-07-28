<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigPublication extends Model
{
    protected $table = 'config_publication';

    protected $fillable = [
        'essais_gratuits_defaut',
        'frais_etude_actifs',
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
            'frais_etude_actifs' => 'boolean',
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
