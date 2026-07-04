<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueConnexion extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'plateforme',
        'ville',
        'pays',
        'statut',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    /**
     * L'utilisateur lié à cet historique.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
