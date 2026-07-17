<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'contenu_html',
        'fichier_pdf',
        'date_generation',
        'date_acceptation',
        'statut_signature',
    ];

    protected function casts(): array
    {
        return [
            'date_generation'  => 'datetime',
            'date_acceptation' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
