<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConfigTypeDocument extends Model
{
    use HasUuids;

    protected $table     = 'config_types_document';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'nom', 'description',
        'formats_acceptes', 'taille_max_octets',
        'commun_tous_roles', 'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'formats_acceptes'  => 'array',
            'commun_tous_roles' => 'boolean',
            'actif'             => 'boolean',
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

    /** Rôles qui requièrent ce document. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            ConfigRoleDeposant::class,
            'config_docs_par_role',
            'type_document_id',
            'role_id'
        )->withPivot('obligatoire')->withTimestamps();
    }

    public function docsParRole(): HasMany
    {
        return $this->hasMany(ConfigDocParRole::class, 'type_document_id');
    }
}
