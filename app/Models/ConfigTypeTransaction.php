<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConfigTypeTransaction extends Model
{
    use HasUuids;

    protected $table     = 'config_types_transaction';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'nom', 'description',
        'est_location', 'demande_unite_prix',
        'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'est_location'      => 'boolean',
            'demande_unite_prix'=> 'boolean',
            'actif'             => 'boolean',
        ];
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /** Retourne les slugs valides depuis la BDD (utilisé dans les validations). */
    public static function slugsValides(): array
    {
        return static::where('actif', true)->pluck('slug')->toArray();
    }
}
