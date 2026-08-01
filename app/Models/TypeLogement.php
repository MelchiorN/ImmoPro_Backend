<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Types de logement configurables par catégorie.
 *
 * Pour la catégorie "appartement", les types par défaut sont :
 *   Studio / F1, F2, F3, F4 et plus.
 *
 * Un administrateur peut en ajouter, modifier l'ordre ou désactiver
 * un type sans toucher au code.
 */
class TypeLogement extends Model
{
    use HasUuids;

    protected $table = 'types_logement';

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'categorie_id',
        'slug',
        'nom',
        'description',
        'est_socle',
        'actif',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'est_socle' => 'boolean',
            'actif'     => 'boolean',
            'ordre'     => 'integer',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retourne les types actifs pour un slug de catégorie donné.
     */
    public static function pourCategorie(string $slugCategorie): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereHas('categorie', fn ($q) => $q->where('slug', $slugCategorie))
            ->actif()
            ->ordered()
            ->get();
    }
}
