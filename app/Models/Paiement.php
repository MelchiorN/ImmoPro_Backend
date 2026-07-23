<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paiement extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'montant',
        'operateur_paiement',
        'reference_transaction',
        'semoa_bill_id',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function recu(): HasOne
    {
        return $this->hasOne(Recu::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public const STATUTS = ['initie', 'en_attente', 'confirme', 'succes', 'echoue'];
}
