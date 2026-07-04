<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otp extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'code',
        'utilise',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'utilise'    => 'boolean',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * Vérifie si l'OTP est encore valide (non utilisé et non expiré).
     */
    public function isValid(): bool
    {
        return !$this->utilise && $this->expired_at?->isFuture();
    }
}
