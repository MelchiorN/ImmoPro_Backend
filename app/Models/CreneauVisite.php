<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreneauVisite extends Model
{
    use HasUuids;

    protected $table     = 'creneaux_visite';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',    // nullable — créneau libre sans bien associé
        'agent_id',
        'date_debut',
        'date_fin',
        'statut',
        'visite_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin'   => 'datetime',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }
}
