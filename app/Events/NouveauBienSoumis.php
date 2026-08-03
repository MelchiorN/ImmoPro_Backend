<?php

namespace App\Events;

use App\Models\Bien;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasted quand un client soumet un nouveau bien.
 * Notifie tous les admins en temps réel.
 */
class NouveauBienSoumis implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Bien $bien) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bien.nouveau';
    }

    public function broadcastWith(): array
    {
        return [
            'id'               => $this->bien->id,
            'titre'            => $this->bien->titre,
            'type_transaction' => $this->bien->type_transaction,
            'adresse'          => $this->bien->adresse,
            'created_at'       => $this->bien->created_at?->toIso8601String(),
        ];
    }
}
