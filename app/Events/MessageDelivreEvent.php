<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasté vers l'expéditeur d'un message dès que le destinataire
 * a reçu le message en temps réel (ACK de délivrance).
 *
 * Canal : private-user.{expediteurId}
 * Event : message.delivre
 *
 * Payload : { message_id, conversation_id, delivre_le }
 */
class MessageDelivreEvent implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $expediteurId,
        public readonly string $delivreLe,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->expediteurId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.delivre';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'      => $this->messageId,
            'conversation_id' => $this->conversationId,
            'delivre_le'      => $this->delivreLe,
        ];
    }
}
