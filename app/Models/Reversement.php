<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reversement extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'proprietaire_id',
        'location_id',
        'montant_a_reverser',
        'statut',
        'date_paiement',
    ];

    protected function casts(): array
    {
        return [
            'montant_a_reverser' => 'decimal:2',
            'date_paiement'      => 'datetime',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public const STATUTS = ['en_attente', 'traite'];
}
