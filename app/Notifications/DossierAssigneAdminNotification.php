<?php

namespace App\Notifications;

use App\Models\Bien;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DossierAssigneAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $bien;
    public $agent;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien, User $agent)
    {
        $this->bien = $bien;
        $this->agent = $agent;
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
            'type' => 'dossier_assigne_admin',
            'bien_id' => $this->bien->id,
            'message' => 'L\'agent ' . $this->agent->name . ' a pris en charge le dossier ' . $this->bien->titre . '.'
        ];
    }
}
