<?php

namespace App\Events;

use App\Models\Bien;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasted quand un bien est assigné à un agent (par admin ou auto-claim).
 * Channels :
 *  - private-admin            → tous les admins
 *  - private-agent.{agentId}  → l'agent qui vient d'être assigné
 */
class DossierAssigneEvent implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public Bien $bien,
        public User $agent,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
            new PrivateChannel("agent.{$this->agent->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dossier.assigne';
    }

    public function broadcastWith(): array
    {
        return [
            'bien_id'    => $this->bien->id,
            'bien_titre' => $this->bien->titre,
            'agent_id'   => $this->agent->id,
            'agent_nom'  => trim("{$this->agent->first_name} {$this->agent->last_name}"),
        ];
    }
}
