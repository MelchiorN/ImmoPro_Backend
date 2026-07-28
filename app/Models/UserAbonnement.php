<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UserAbonnement extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'plan_id',
        'nb_publications_initiales',
        'nb_publications_restantes',
        'statut',
        'date_achat',
    ];

    protected function casts(): array
    {
        return [
            'date_achat' => 'datetime',
        ];
    }

    public const STATUTS = ['actif', 'epuise', 'annule'];

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAbonnement::class, 'plan_id');
    }

    /**
     * Paiements liés à cet abonnement (relation polymorphique)
     */
    public function paiements(): MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Décrémente le solde et passe le statut à 'epuise' si nécessaire.
     */
    public function consommerUnePublication(): void
    {
        $this->decrement('nb_publications_restantes');
        if ($this->nb_publications_restantes <= 0) {
            $this->update(['statut' => 'epuise']);
        }
    }
}
