<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NouveauBienAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $bien;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien)
    {
        $this->bien = $bien;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nouveau_dossier_admin',
            'bien_id' => $this->bien->id,
            'message' => 'Un nouveau bien a été soumis sur la plateforme : ' . $this->bien->titre . '.'
        ];
    }
}
