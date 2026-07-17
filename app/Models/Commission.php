<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'paiement_id',
        'pourcentage_applique',
        'montant_gagne',
        'date_prelevement',
    ];

    protected function casts(): array
    {
        return [
            'pourcentage_applique' => 'decimal:2',
            'montant_gagne'        => 'decimal:2',
            'date_prelevement'     => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
