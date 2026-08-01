<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DecisionBienAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $bien;
    public $decision; // 'approuve' ou 'rejete'

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien, string $decision)
    {
        $this->bien = $bien;
        $this->decision = $decision;
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
        $msg = $this->decision === 'approuve' 
            ? '✅ Le bien ' . $this->bien->titre . ' a été approuvé par son agent.'
            : '❌ Le bien ' . $this->bien->titre . ' a été rejeté par son agent.';

        return [
            'type' => 'decision_bien_admin',
            'decision' => $this->decision,
            'bien_id' => $this->bien->id,
            'message' => $msg
        ];
    }
}
