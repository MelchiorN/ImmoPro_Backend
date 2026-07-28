<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanAbonnement extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nom',
        'description',
        'nb_publications',
        'prix',
        'ordre',
        'est_actif',
    ];

    protected function casts(): array
    {
        return [
            'prix'       => 'decimal:2',
            'est_actif'  => 'boolean',
        ];
    }

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function userAbonnements(): HasMany
    {
        return $this->hasMany(UserAbonnement::class, 'plan_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('est_actif', true)->orderBy('ordre');
    }
}
