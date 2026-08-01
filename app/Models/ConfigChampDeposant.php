<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigChampDeposant extends Model
{
    use HasUuids;

    protected $table     = 'config_champs_deposant';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'role_id', 'nom_champ', 'label', 'placeholder',
        'type_champ', 'options_enum',
        'obligatoire', 'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'options_enum' => 'array',
            'obligatoire'  => 'boolean',
            'actif'        => 'boolean',
        ];
    }

    // Types de champ valides
    public const TYPES = ['texte', 'email', 'telephone', 'enum', 'booleen'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(ConfigRoleDeposant::class, 'role_id');
    }
}
