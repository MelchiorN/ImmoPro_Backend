<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Broadcasted immédiatement après l'enregistrement d'une notification en base.
 * Permet au frontend et au mobile de rafraîchir le compteur de notifs en temps réel.
 *
 * Channel : private-user.{userId} → uniquement le destinataire
 */
class NouvelleNotificationEvent implements ShouldBroadcast
{
    public function __construct(
        public string $userId,
        public string $type,
        public string $titre,
        public string $message,
        public array  $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.nouvelle';
    }

    public function broadcastWith(): array
    {
        return [
            'type'    => $this->type,
            'titre'   => $this->titre,
            'message' => $this->message,
            'data'    => $this->data,
        ];
    }
}
