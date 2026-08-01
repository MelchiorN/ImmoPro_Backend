<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

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

    /**
     * Active cet abonnement en tenant compte d'un éventuel abonnement déjà actif.
     *
     * Règle : si l'utilisateur a déjà un abonnement actif avec des publications
     * restantes, les publications du nouveau plan sont additionnées sur cet
     * abonnement existant et le présent enregistrement est marqué 'epuise'
     * (fusionné). Sinon, le présent enregistrement passe simplement à 'actif'.
     *
     * @return self  L'abonnement qui porte désormais les publications (existant ou self).
     */
    public function activerEnFusionnantSiNecessaire(): self
    {
        // Chercher un abonnement actif existant (hors le présent enregistrement)
        $abonnementExistant = self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->where('statut', 'actif')
            ->where('nb_publications_restantes', '>', 0)
            ->oldest('date_achat')
            ->first();

        if ($abonnementExistant) {
            // Additionner les publications sur l'abonnement existant
            $pubsAjouter = (int) $this->nb_publications_initiales;

            $abonnementExistant->increment('nb_publications_restantes', $pubsAjouter);
            $abonnementExistant->increment('nb_publications_initiales', $pubsAjouter);

            // Marquer le nouvel enregistrement comme fusionné (épuisé immédiatement)
            $this->update([
                'statut'                    => 'epuise',
                'nb_publications_restantes' => 0,
            ]);

            Log::info('[UserAbonnement] Publications fusionnées sur abonnement existant', [
                'user_id'               => $this->user_id,
                'abonnement_cible_id'   => $abonnementExistant->id,
                'abonnement_source_id'  => $this->id,
                'pubs_ajoutees'         => $pubsAjouter,
                'nouveau_total'         => $abonnementExistant->fresh()->nb_publications_restantes,
            ]);

            return $abonnementExistant->fresh();
        }

        // Pas d'abonnement actif existant : activation normale
        $this->update(['statut' => 'actif']);

        return $this;
    }
}
