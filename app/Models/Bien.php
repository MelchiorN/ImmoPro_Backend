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
        'surface',
        'nb_pieces',
        'nb_salles_bain',
        'adresse',
        'latitude',
        'longitude',
        'statut',
        'note_admin',
        'agent_id',
        'publie_le',
    ];

    protected function casts(): array
    {
        return [
            'prix'         => 'decimal:2',
            'surface'      => 'decimal:2',
            'latitude'     => 'decimal:7',
            'longitude'    => 'decimal:7',
            'publie_le'    => 'datetime',
        ];
    }

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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Statuts possibles. */
    public const STATUTS = ['brouillon', 'en_attente', 'en_cours', 'publie', 'rejete', 'archive'];

    /** Vrai si le bien peut encore être modifié par le propriétaire. */
    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete']);
    }

    /** Vrai si le bien est en cours de vérification par un agent. */
    public function estEnCours(): bool
    {
        return $this->statut === 'en_cours';
    }

    /** Types de biens qui n'ont pas de pièces/salles de bain. */
    public static function typeSansChambres(): array
    {
        return ['terrain'];
    }
}
