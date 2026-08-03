<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Notifications\Notification;

/**
 * Notification admin quand un agent approuve ou rejette un bien.
 *
 * ⚠️  Canal 'database' natif DÉSACTIVÉ — la persistance en DB est gérée
 *     par NotificationService qui remplit les colonnes titre/message/type
 *     de la table notifications custom.
 *     Cette classe n'existe plus que comme DTO pour transporter les données
 *     vers les endroits qui font encore ->notify().
 */
class DecisionBienAdminNotification extends Notification
{
    public function __construct(
        public readonly Bien   $bien,
        public readonly string $decision, // 'approuve' | 'rejete'
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
