<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'contenu',
        'delivre_le',
        'lu_le',
    ];

    protected function casts(): array
    {
        return [
            'delivre_le' => 'datetime',
            'lu_le'      => 'datetime',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(MessageSuppression::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vrai si le message est masqué pour l'utilisateur donné.
     * Masqué si : suppression "pour moi" par cet user OU suppression "pour tous" par n'importe qui.
     */
    public function estSupprimePour(User $user): bool
    {
        return $this->suppressions()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('pour_tous', true);
            })
            ->exists();
    }

    /**
     * Vrai si le message a été supprimé pour tous.
     */
    public function estSupprimePourTous(): bool
    {
        return $this->suppressions()->where('pour_tous', true)->exists();
    }
}
