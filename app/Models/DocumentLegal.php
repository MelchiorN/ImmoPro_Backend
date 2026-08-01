<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle DocumentLegal
 *
 * Représente un document légal (CGU, CGV, Politique de confidentialité, À propos)
 * paramétrable depuis l'interface d'administration et affiché sur le mobile.
 *
 * @property int    $id
 * @property string $slug
 * @property string $titre
 * @property string|null $description
 * @property string $contenu
 * @property bool   $actif
 * @property int    $version
 * @property \Illuminate\Support\Carbon|null $date_maj
 */
class DocumentLegal extends Model
{
    protected $table = 'documents_legaux';

    protected $fillable = [
        'slug',
        'titre',
        'description',
        'contenu',
        'actif',
        'version',
        'date_maj',
    ];

    protected function casts(): array
    {
        return [
            'actif'    => 'boolean',
            'version'  => 'integer',
            'date_maj' => 'datetime',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filtre uniquement les documents actifs (visibles sur le mobile).
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Retourne le document par son slug (ou null).
     */
    public static function bySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Incrémente la version et met à jour la date éditoriale.
     */
    public function incrementVersion(): void
    {
        $this->increment('version');
        $this->update(['date_maj' => now()]);
    }

    // ── Slugs constants ───────────────────────────────────────────────────────

    public const SLUG_CGU              = 'cgu';
    public const SLUG_CGV              = 'cgv';
    public const SLUG_CONFIDENTIALITE  = 'politique_confidentialite';
    public const SLUG_A_PROPOS         = 'a_propos';
}
