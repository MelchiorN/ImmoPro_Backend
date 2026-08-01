<?php

namespace App\Models;

use App\Services\DureeService;
use Illuminate\Database\Eloquent\Model;

class RappelVisiteConfig extends Model
{
    protected $table = 'rappels_visite_config';

    protected $fillable = ['valeur', 'unite', 'est_jour_j', 'heure_jour_j', 'actif', 'ordre'];

    protected function casts(): array
    {
        return [
            'actif'      => 'boolean',
            'est_jour_j' => 'boolean',
            'valeur'     => 'integer',
            'ordre'      => 'integer',
        ];
    }

    /** Convertit en minutes (ignoré pour est_jour_j). */
    public function toMinutes(): int
    {
        return DureeService::toMinutes($this->valeur, $this->unite);
    }

    /** Libellé lisible pour l'admin. */
    public function label(): string
    {
        if ($this->est_jour_j) {
            return "Le jour même (à {$this->heure_jour_j})";
        }
        return "{$this->valeur} {$this->unite} avant";
    }
}
