<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enregistre chaque recherche de bien effectuée par un utilisateur authentifié.
 * Utilisé par le moteur de recommandation IA pour personnaliser les suggestions.
 *
 * @property string      $id
 * @property string      $user_id
 * @property string|null $query_text
 * @property string|null $type_bien
 * @property string|null $type_transaction
 * @property float|null  $prix_min
 * @property float|null  $prix_max
 * @property string|null $ville
 * @property float|null  $lat
 * @property float|null  $lng
 * @property int         $nb_resultats
 */
class HistoriqueRecherche extends Model
{
    use HasUuids;

    protected $table = 'historique_recherches';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'query_text',
        'type_bien',
        'type_transaction',
        'prix_min',
        'prix_max',
        'ville',
        'lat',
        'lng',
        'nb_resultats',
    ];

    protected function casts(): array
    {
        return [
            'prix_min'     => 'decimal:2',
            'prix_max'     => 'decimal:2',
            'lat'          => 'float',
            'lng'          => 'float',
            'nb_resultats' => 'integer',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Garde uniquement les N dernières recherches par utilisateur.
     * À appeler depuis un job de nettoyage ou après chaque insertion.
     */
    public static function purgerPourUtilisateur(string $userId, int $garder = 50): void
    {
        $idsAGarder = static::where('user_id', $userId)
            ->latest()
            ->take($garder)
            ->pluck('id');

        static::where('user_id', $userId)
            ->whereNotIn('id', $idsAGarder)
            ->delete();
    }
}
