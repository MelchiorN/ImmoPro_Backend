<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nom',
        'slug',
        'description',
        'actif',
        'ordre_affichage',
        'pourcentage_commission',
    ];

    protected function casts(): array
    {
        return [
            'actif'                  => 'boolean',
            'pourcentage_commission' => 'decimal:2',
        ];
    }

    /**
     * Calcule le prix public d'un bien en appliquant la commission.
     * prix_public = prix + (prix × pourcentage_commission / 100)
     */
    public function calculerPrixPublic(float $prixProprietaire): float
    {
        $commission = (float) $this->pourcentage_commission;
        return round($prixProprietaire + ($prixProprietaire * $commission / 100), 2);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function attributs(): HasMany
    {
        return $this->hasMany(AttributDefinition::class, 'categorie_id')
                    ->orderBy('ordre_affichage');
    }

    public function attributsActifs(): HasMany
    {
        return $this->hasMany(AttributDefinition::class, 'categorie_id')
                    ->where('actif', true)
                    ->orderBy('ordre_affichage');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retourne la catégorie correspondant à un type_bien (slug = type_bien).
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
