<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Notifications\Notification;

/**
 * Notification admin quand un nouveau bien est soumis.
 *
 * ⚠️  Canal 'database' natif DÉSACTIVÉ — la persistance en DB est gérée
 *     par NotificationService.
 */
class NouveauBienAdminNotification extends Notification
{
    public function __construct(public readonly Bien $bien) {}

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
