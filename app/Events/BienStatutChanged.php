<?php

namespace App\Events;

use App\Models\Bien;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasted quand le statut d'un bien change (admin ou agent).
 * Channels :
 *  - private-admin            → tous les admins connectés
 *  - private-agent.{agentId}  → l'agent assigné (si présent)
 *  - private-user.{userId}    → le propriétaire du bien
 */
class BienStatutChanged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Bien $bien) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin'),
            new PrivateChannel("user.{$this->bien->user_id}"),
        ];

        if ($this->bien->agent_id) {
            $channels[] = new PrivateChannel("agent.{$this->bien->agent_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'bien.statut.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'     => $this->bien->id,
            'titre'  => $this->bien->titre,
            'statut' => $this->bien->statut,
        ];
    }
}
