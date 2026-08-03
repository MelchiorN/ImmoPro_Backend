<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasté immédiatement après l'envoi d'un message dans une conversation.
 *
 * Channels :
 *  - private-agent.{agentId}  → l'agent (côté web Nuxt)
 *  - private-user.{agentId}   → l'agent (côté mobile, si besoin)
 *
 * Le message complet est transmis pour que le destinataire puisse
 * afficher la bulle en temps réel sans recharger.
 */
class NouveauMessageEvent implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string  $destinataireId,  // ID de l'agent (le receveur)
        public readonly string  $destinataireRole, // 'agent' | 'client'
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        // Canal rôle-spécifique (web agent sidebar badge)
        if ($this->destinataireRole === 'agent') {
            $channels[] = new PrivateChannel("agent.{$this->destinataireId}");
        }

        // Canal user générique (mobile + badge notifications)
        $channels[] = new PrivateChannel("user.{$this->destinataireId}");

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.nouveau';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;
        $sender       = $this->message->sender;

        return [
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'contenu_apercu'  => mb_substr($this->message->contenu, 0, 80),
            'envoye_le'       => $this->message->created_at?->toIso8601String(),
            'sender' => [
                'id'    => $sender?->id,
                'nom'   => trim(($sender?->first_name ?? '') . ' ' . ($sender?->last_name ?? '')),
            ],
            'bien' => $conversation?->bien ? [
                'id'    => $conversation->bien_id,
                'titre' => $conversation->bien->titre ?? '',
            ] : null,
        ];
    }
}
