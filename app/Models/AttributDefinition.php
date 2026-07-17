<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributDefinition extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'categorie_id',
        'nom_champ',
        'label_affiche',
        'type_champ',
        'options_enum',
        'obligatoire',
        'est_socle',
        'actif',
        'ordre_affichage',
    ];

    protected function casts(): array
    {
        return [
            'options_enum'    => 'array',
            'obligatoire'     => 'boolean',
            'est_socle'       => 'boolean',
            'actif'           => 'boolean',
            'ordre_affichage' => 'integer',
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

    public function scopeObligatoire($query)
    {
        return $query->where('obligatoire', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre_affichage');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Types de champs supportés */
    public const TYPES = ['texte', 'nombre', 'booleen', 'enum', 'date'];

    /**
     * Vérifie si une valeur est valide pour ce champ.
     * Utilisé dans la validation dynamique du StoreBienRequest.
     */
    public function validerValeur(mixed $valeur): bool
    {
        if ($valeur === null) {
            return ! $this->obligatoire;
        }

        return match ($this->type_champ) {
            'nombre'  => is_numeric($valeur),
            'booleen' => is_bool($valeur) || in_array($valeur, [0, 1, '0', '1', true, false], true),
            'enum'    => in_array($valeur, $this->options_enum ?? [], true),
            'date'    => (bool) strtotime($valeur),
            'texte'   => is_string($valeur),
            default   => true,
        };
    }
}
