<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConfigUnitePrix extends Model
{
    use HasUuids;

    protected $table     = 'config_unites_prix';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = ['slug', 'nom', 'description', 'actif', 'ordre'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    public static function slugsValides(): array
    {
        return static::where('actif', true)->pluck('slug')->toArray();
    }
}
