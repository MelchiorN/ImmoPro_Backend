<?php

namespace App\Notifications;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientIndisponibleNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Visite $visite) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $bien   = $this->visite->bien;
        $client = $this->visite->client;
        $nom    = $client ? trim("{$client->first_name} {$client->last_name}") : 'Le client';

        return [
            'type'         => 'client_indisponible',
            'titre'        => '⚠️ Client indisponible — reproposition requise',
            'message'      => "{$nom} est indisponible pour les créneaux proposés pour « {$bien?->titre} ». Veuillez proposer de nouveaux créneaux.",
            'visite_id'    => $this->visite->id,
            'bien_id'      => $this->visite->bien_id,
            'bien_titre'   => $bien?->titre,
            'client_nom'   => $nom,
            'nb_tentatives'=> $this->visite->nb_indisponibilites,
        ];
    }
}
