<?php

namespace App\Notifications;

use App\Models\Visite;
use Illuminate\Notifications\Notification;

/**
 * Notification agent quand un client se déclare indisponible sur tous les créneaux.
 *
 * ⚠️  Canal 'database' natif DÉSACTIVÉ — la persistance en DB est gérée
 *     par NotificationService.
 */
class ClientIndisponibleNotification extends Notification
{
    public function __construct(public readonly Visite $visite) {}

    /** Aucun canal natif — on ne passe plus par la queue Laravel. */
    public function via(object $notifiable): array
    {
        return [];
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
