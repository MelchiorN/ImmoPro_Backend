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
        ];
    }

    // Rôles de déposant valides
    public const ROLES_DEPOSANT = ['proprietaire', 'agence', 'mandataire', 'heritier', 'autre'];

    // Unités de prix valides
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
    public const STATUTS = ['brouillon', 'en_attente', 'en_cours', 'valide', 'publie', 'rejete', 'archive'];

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

    /** Types de biens qui n'ont pas de pièces/salles de bain. */
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
}
