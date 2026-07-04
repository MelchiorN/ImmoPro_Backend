<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceIdentite extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'numero',
        'fichier_recto',
        'fichier_verso',
        'date_expiration',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_expiration' => 'date',
        ];
    }

    /**
     * L'utilisateur propriétaire de cette pièce d'identité.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
