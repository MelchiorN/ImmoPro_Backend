<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSuppression extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    // Pas de updated_at — une suppression ne se modifie pas
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'pour_tous',
        'supprime_le',
    ];

    protected function casts(): array
    {
        return [
            'pour_tous'    => 'boolean',
            'supprime_le'  => 'datetime',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
