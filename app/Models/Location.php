<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',
        'locataire_id',
        'proprietaire_id',
        'date_debut',
        'date_fin',
        'duree_mois',
        'prix_proprietaire',
        'montant_commission',
        'montant_total',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut'         => 'date',
            'date_fin'           => 'date',
            'prix_proprietaire'  => 'decimal:2',
            'montant_commission' => 'decimal:2',
            'montant_total'      => 'decimal:2',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locataire_id');
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function contrat(): HasOne
    {
        return $this->hasOne(Contrat::class);
    }

    public function paiement(): HasOne
    {
        return $this->hasOne(Paiement::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function reversement(): HasOne
    {
        return $this->hasOne(Reversement::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public const STATUTS = [
        'en_attente_contrat',
        'en_attente_paiement',
        'actif',
        'termine',
        'annule',
    ];
}
