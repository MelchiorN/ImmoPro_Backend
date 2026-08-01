<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConfigRoleDeposant extends Model
{
    use HasUuids;

    protected $table     = 'config_roles_deposant';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'nom', 'description',
        'est_proprietaire', 'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'est_proprietaire' => 'boolean',
            'actif'            => 'boolean',
        ];
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    public static function slugsValides(): array
    {
        return static::where('actif', true)->pluck('slug')->toArray();
    }

    /** Champs personnels du propriétaire réel demandés pour ce rôle. */
    public function champsDeposant(): HasMany
    {
        return $this->hasMany(ConfigChampDeposant::class, 'role_id')
                    ->where('actif', true)
                    ->orderBy('ordre');
    }

    /** Tous les champs (actifs ou non) — pour l'administration. */
    public function tousLesChamps(): HasMany
    {
        return $this->hasMany(ConfigChampDeposant::class, 'role_id')
                    ->orderBy('ordre');
    }

    /** Documents liés à ce rôle avec le pivot obligatoire/optionnel. */
    public function typesDocument(): BelongsToMany
    {
        return $this->belongsToMany(
            ConfigTypeDocument::class,
            'config_docs_par_role',
            'role_id',
            'type_document_id'
        )->withPivot('obligatoire')->withTimestamps();
    }

    public function docsParRole(): HasMany
    {
        return $this->hasMany(ConfigDocParRole::class, 'role_id');
    }
}
