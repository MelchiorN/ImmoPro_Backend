<?php

namespace App\Events;

use App\Models\Visite;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasted quand une visite change de statut.
 * Channels :
 *  - private-admin                → tous les admins
 *  - private-agent.{agentId}      → l'agent de la visite
 *  - private-user.{proprietaireId}→ le propriétaire (si visite de vérification)
 *  - private-user.{clientId}      → le client (si visite client)
 */
class VisiteStatutChanged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Visite $visite) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin'),
        ];

        if ($this->visite->agent_id) {
            $channels[] = new PrivateChannel("agent.{$this->visite->agent_id}");
        }

        // Visite de vérification → notifier le propriétaire du bien
        if ($this->visite->bien?->user_id) {
            $channels[] = new PrivateChannel("user.{$this->visite->bien->user_id}");
        }

        // Visite client → notifier le client
        if ($this->visite->client_id && $this->visite->client_id !== $this->visite->bien?->user_id) {
            $channels[] = new PrivateChannel("user.{$this->visite->client_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'visite.statut.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->visite->id,
            'statut'      => $this->visite->statut,
            'type_visite' => $this->visite->type_visite,
            'bien_id'     => $this->visite->bien_id,
            'bien_titre'  => $this->visite->bien?->titre,
            'date_visite' => $this->visite->date_visite?->toIso8601String(),
        ];
    }
}
