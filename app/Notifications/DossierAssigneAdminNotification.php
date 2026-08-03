<?php

namespace App\Notifications;

use App\Models\Bien;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Notification admin quand un agent prend en charge un dossier.
 *
 * ⚠️  Canal 'database' natif DÉSACTIVÉ — la persistance en DB est gérée
 *     par NotificationService.
 */
class DossierAssigneAdminNotification extends Notification
{
    public function __construct(
        public readonly Bien $bien,
        public readonly User $agent,
    ) {}

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
