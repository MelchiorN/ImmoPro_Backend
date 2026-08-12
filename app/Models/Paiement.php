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
        'payment_url',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    /**
     * Accessor pour garantir que payment_url utilise toujours le guichet interactif (/facture/)
     */
    public function getPaymentUrlAttribute(?string $value): ?string
    {
        $isSandbox = config('services.semoa.env', 'sandbox') === 'sandbox';
        $domain = $isSandbox ? 'sandbox.cashpay.tg' : 'cashpay.tg';

        if (empty($value) && !empty($this->semoa_bill_id)) {
            return "https://{$domain}/facture/{$this->semoa_bill_id}";
        }

        if (!empty($value)) {
            $value = str_replace('sandbox-bill.cashpay.tg/', 'sandbox.cashpay.tg/facture/', $value);
            $value = str_replace('bill.cashpay.tg/', 'cashpay.tg/facture/', $value);
            $value = str_replace('lk.semoa.tg/', "{$domain}/facture/", $value);
        }

        return $value;
    }

    public const STATUTS        = ['initie', 'en_attente', 'confirme', 'succes', 'echoue'];
    public const TYPES_PAIEMENT = ['location', 'abonnement', 'frais_etude', 'visite'];

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
