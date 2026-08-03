<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'agent_id',
        'client_id',
        'bien_id',
        'dernier_message_le',
    ];

    protected function casts(): array
    {
        return [
            'dernier_message_le' => 'datetime',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function dernierMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Retourne l'autre participant par rapport à l'utilisateur courant.
     */
    public function autreParticipant(User $user): ?User
    {
        if ($user->id === $this->agent_id) {
            return $this->client;
        }

        return $this->agent;
    }

    /**
     * Nombre de messages non lus pour un utilisateur donné.
     */
    public function messagesNonLus(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('lu_le')
            ->whereNotExists(function ($q) use ($user) {
                $q->select('id')
                  ->from('message_suppressions')
                  ->whereColumn('message_suppressions.message_id', 'messages.id')
                  ->where(function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->orWhere('pour_tous', true);
                  });
            })
            ->count();
    }
}
