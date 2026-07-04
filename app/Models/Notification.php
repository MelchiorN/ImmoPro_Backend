<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'data',
        'lu',
        'canal',
        'lu_at',
    ];

    protected function casts(): array
    {
        return [
            'data'  => 'array',
            'lu'    => 'boolean',
            'lu_at' => 'datetime',
        ];
    }

    /**
     * L'utilisateur propriétaire de cette notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
