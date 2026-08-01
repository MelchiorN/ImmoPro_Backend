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
        'frais_etude_pourcentage',
        'a_chambres',
        'a_superficie_terrain',
        'documents_optionnels',
        'visite_tarif_type',
        'visite_pourcentage',
        'visite_tarif_fixe',
    ];

    protected function casts(): array
    {
        return [
            'actif'                   => 'boolean',
            'pourcentage_commission'  => 'decimal:2',
            'frais_etude_pourcentage' => 'decimal:2',
            'a_chambres'              => 'boolean',
            'a_superficie_terrain'    => 'boolean',
            'documents_optionnels'    => 'array',
            'visite_pourcentage'      => 'decimal:2',
            'visite_tarif_fixe'       => 'decimal:2',
        ];
    }

    /**
     * Calcule le tarif de la visite pour cette catégorie.
     */
    public function calculerPrixVisite(float $prixProprietaire): float
    {
        if ($this->visite_tarif_type === 'pourcentage') {
            $pourcentage = (float) ($this->visite_pourcentage ?? 0);
            return round($prixProprietaire * $pourcentage / 100, 2);
        }
        return (float) ($this->visite_tarif_fixe ?? 0);
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

    /**
     * Calcule les frais d'étude de dossier pour un bien de cette catégorie.
     * frais = prix × frais_etude_pourcentage / 100
     *
     * Retourne 0 si les frais d'étude sont désactivés globalement.
     */
    public function calculerFraisEtude(float $prixBien): float
    {
        $pourcentage = (float) $this->frais_etude_pourcentage;
        if ($pourcentage <= 0) {
            return 0.0;
        }
        return round($prixBien * $pourcentage / 100, 2);
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

    /**
     * Types de logement configurables (ex: Studio/F1, F2, F3, F4+).
     * Utilisé principalement pour la catégorie "appartement".
     */
    public function typesLogement(): HasMany
    {
        return $this->hasMany(TypeLogement::class, 'categorie_id')
                    ->where('actif', true)
                    ->orderBy('ordre');
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
    public static function findBySlug(?string $slug): ?self
    {
        if (is_null($slug)) {
            return null;
        }
        return static::where('slug', $slug)->first();
    }
}
