<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bien extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType  = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'type_bien',
        'type_transaction',
        'titre',
        'description',
        'desc_personnalisee',  // Générée par Gemini à l'approbation, mise en cache ici
        'prix',
        'prix_public',
        'unite_prix',
        'avance_mois',
        'caution',
        'surface',
        'superficie',
        'nb_pieces',
        'nb_salles_bain',
        'caracteristiques',
        'adresse',
        'latitude',
        'longitude',
        'statut',
        'note_admin',
        'agent_id',
        'publie_le',
        'locked_until',
        'publication_auto',
        // Déposant
        'role_deposant',
        'proprietaire_nom',
        'proprietaire_prenom',
        'proprietaire_sexe',
        'proprietaire_nationalite',
        'proprietaire_telephone',
        'proprietaire_email',
        'proprietaire_adresse',
        // Frais d'étude
        'frais_etude_statut',
        'frais_etude_paiement_id',
        // Workflow SLA
        'submitted_at',
        'claimed_at',
        'sla1_alerted_at',
        'sla2_alerted_at',
        'prix_visite',
    ];

    protected function casts(): array
    {
        return [
            'prix'              => 'decimal:2',
            'prix_public'       => 'decimal:2',
            'caution'           => 'decimal:2',
            'surface'           => 'decimal:2',
            'superficie'        => 'decimal:2',
            'latitude'          => 'decimal:7',
            'longitude'         => 'decimal:7',
            'publie_le'         => 'datetime',
            'locked_until'      => 'datetime',
            'caracteristiques'  => 'array',
            // Workflow SLA
            'submitted_at'      => 'datetime',
            'claimed_at'        => 'datetime',
            'sla1_alerted_at'   => 'datetime',
            'sla2_alerted_at'   => 'datetime',
            'prix_visite'       => 'decimal:2',
        ];
    }

    protected static function booted()
    {
        static::created(function ($bien) {
            if ($bien->statut === 'en_attente') {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($bien) {
                    app(\App\Services\BienDescriptionService::class)->enrichirEtSauvegarder($bien);
                });
            }
        });

        static::updated(function ($bien) {
            if ($bien->wasChanged('statut') && $bien->statut === 'en_attente') {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($bien) {
                    app(\App\Services\BienDescriptionService::class)->enrichirEtSauvegarder($bien);
                });
            }
        });
    }

    // Rôles de déposant valides — DÉPRÉCIÉ : utiliser ConfigRoleDeposant::slugsValides()
    // Conservé pour rétrocompatibilité uniquement
    public const ROLES_DEPOSANT = ['proprietaire', 'agence', 'mandataire', 'heritier', 'autre'];

    // Unités de prix valides — DÉPRÉCIÉ : utiliser ConfigUnitePrix::slugsValides()
    // Conservé pour rétrocompatibilité uniquement
    public const UNITES_PRIX = ['jour', 'semaine', 'mois', 'annee'];

    // Statuts frais d'étude
    public const FRAIS_ETUDE_STATUTS = ['non_requis', 'en_attente_paiement', 'paye'];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Seuls les biens publiés (accès public). */
    public function scopePublie($query)
    {
        return $query->where('statut', 'publie');
    }

    /** Filtre par type de bien. */
    public function scopeTypeBien($query, string $type)
    {
        return $query->where('type_bien', $type);
    }

    /** Filtre par type de transaction. */
    public function scopeTypeTransaction($query, string $type)
    {
        return $query->where('type_transaction', $type);
    }

    /** Filtre par fourchette de prix. */
    public function scopePrixEntre($query, ?float $min, ?float $max)
    {
        if ($min) $query->where('prix', '>=', $min);
        if ($max) $query->where('prix', '<=', $max);
        return $query;
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function medias(): HasMany
    {
        return $this->hasMany(MediaBien::class)->orderBy('ordre');
    }

    public function mediasPrincipaux(): HasMany
    {
        return $this->hasMany(MediaBien::class)->where('est_principale', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentBien::class);
    }

    public function rapport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Rapport::class);
    }

    /**
     * Catégorie liée via le slug = type_bien.
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'type_bien', 'slug');
    }

    public function getCategorie(): ?Categorie
    {
        if (is_null($this->type_bien)) {
            return null;
        }
        return $this->categorie ?? Categorie::findBySlug($this->type_bien);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** Vérifie si le bien est temporairement verrouillé. */
    public function estVerrouille(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /** Verrouille le bien pour une durée donnée (en minutes). */
    public function verrouiller(int $minutes = 15): void
    {
        $this->update(['locked_until' => now()->addMinutes($minutes)]);
    }

    /** Déverrouille le bien. */
    public function deverrouiller(): void
    {
        $this->update(['locked_until' => null]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Statuts possibles. */
    public const STATUTS = ['brouillon', 'en_attente', 'en_cours', 'valide', 'publie', 'rejete', 'retire', 'archive'];

    /** Vrai si le bien peut encore être modifié par le propriétaire. */
    public function estModifiable(): bool
    {
        return $this->statut !== 'archive';
    }
    /** Vrai si le bien est en cours de vérification par un agent. */
    public function estEnCours(): bool
    {
        return $this->statut === 'en_cours';
    }

    /** Types de biens qui n'ont pas de pièces/salles de bain.
     *  DÉPRÉCIÉ : utiliser Categorie->a_chambres depuis la BDD.
     *  Conservé comme fallback si la migration n'est pas encore appliquée.
     */
    public static function typeSansChambres(): array
    {
        return ['terrain', 'bureau', 'commerce', 'entrepot'];
    }

    public function favorisPar(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favoris', 'bien_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Paiement des frais d'étude lié à ce bien.
     */
    public function fraisEtudePaiement(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'frais_etude_paiement_id');
    }

    /**
     * Tous les paiements de frais d'étude liés à ce bien (polymorphique).
     */
    public function paiements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    // ─── Helpers déposant ─────────────────────────────────────────────────────

    /** Vrai si le déposant est le propriétaire lui-même. */
    public function deposantEstProprietaire(): bool
    {
        return $this->role_deposant === 'proprietaire' || $this->role_deposant === null;
    }

    /** Vrai si les frais d'étude ont été payés (ou ne sont pas requis). */
    public function fraisEtudeOk(): bool
    {
        return in_array($this->frais_etude_statut, ['non_requis', 'paye']);
    }

    public function visites(): HasMany
    {
        return $this->hasMany(Visite::class);
    }

    /**
     * Retourne le prix de visite effectif du bien.
     * 1. Utilise prix_visite s'il est défini sur le bien
     * 2. Fallback : calcule depuis la configuration de la catégorie
     * 3. Retourne null si aucun tarif configuré nulle part
     */
    public function getPrixVisiteEffectif(): ?float
    {
        if ($this->prix_visite && (float) $this->prix_visite > 0) {
            return (float) $this->prix_visite;
        }

        $cat = $this->getCategorie();
        if (! $cat) {
            return null;
        }

        if ($cat->visite_tarif_type === 'pourcentage' && $cat->visite_pourcentage > 0) {
            $montant = $cat->calculerPrixVisite((float) $this->prix);
            return $montant > 0 ? $montant : null;
        }

        if ($cat->visite_tarif_type === 'fixe_manuel' && $cat->visite_tarif_fixe > 0) {
            return (float) $cat->visite_tarif_fixe;
        }

        return null;
    }

    /**
     * Vrai si l'utilisateur a payé les frais de visite pour ce bien.
     */
    public function hasPaidVisit(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Vérifier s'il y a un paiement confirmé pour une visite de ce bien par ce client
        return Visite::where('bien_id', $this->id)
            ->where('client_id', $user->id)
            ->where('est_payee', true)
            ->exists();
    }

    /**
     * Vrai si l'utilisateur peut voir les coordonnées GPS / carte (déposant du bien, admin, agent ou visite payée).
     */
    public function canSeeGps(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array($user->role, ['admin', 'agent']) || $this->user_id === $user->id) {
            return true;
        }

        return $this->hasPaidVisit($user);
    }

    /**
     * Vrai si l'utilisateur peut voir les coordonnées de contact du propriétaire.
     * Les coordonnées du propriétaire s'affichent QUE quand il y a un paiement effectif (ou pour admin/agent).
     * Le déposant du bien a la localisation GPS active mais N'A PAS les coordonnées tant qu'il n'y a pas de paiement.
     */
    public function canSeeProprioContact(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array($user->role, ['admin', 'agent'])) {
            return true;
        }

        return $this->hasPaidVisit($user);
    }
}
