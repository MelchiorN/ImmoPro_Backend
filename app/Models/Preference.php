<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preference extends Model
{
    protected $fillable = [
        'user_id',
        'notifications_email',
        'notifications_push',
        'notifications_sms',
        'langue',
        'devise',
        'types_biens_preferes',
        'villes_preferees',
        'budget_min',
        'budget_max',
    ];

    protected function casts(): array
    {
        return [
            'notifications_email'  => 'boolean',
            'notifications_push'   => 'boolean',
            'notifications_sms'    => 'boolean',
            'types_biens_preferes' => 'array',
            'villes_preferees'     => 'array',
            'budget_min'           => 'decimal:2',
            'budget_max'           => 'decimal:2',
        ];
    }

    /**
     * L'utilisateur propriétaire de ces préférences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
