<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Paiement extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'type_paiement',
        'payable_type',
        'payable_id',
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

    public const STATUTS        = ['initie', 'en_attente', 'confirme', 'succes', 'echoue'];
    public const TYPES_PAIEMENT = ['location', 'abonnement', 'frais_etude'];

    // ─── Relations ─────────────────────────────────────────────────────────────

    /**
     * Relation polymorphique : Location | UserAbonnement | Bien
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Conservé pour rétro-compatibilité avec le module location existant
     */
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
}
