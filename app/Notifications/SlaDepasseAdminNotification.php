<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaDepasseAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $bien;
    public $typeSla; // 'sla1' ou 'sla2'
    public $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien, string $typeSla)
    {
        $this->bien = $bien;
        $this->typeSla = $typeSla;
        if ($typeSla === 'sla1') {
            $this->message = "Alerte SLA 1 : Le bien {$bien->titre} est en attente d'agent depuis trop longtemps.";
        } else {
            $this->message = "Alerte SLA 2 : Le bien {$bien->titre} n'a pas eu d'évolution depuis trop longtemps.";
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Alerte SLA Dépassé — ' . $this->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line($this->message)
            ->action('Voir le dossier', url('/admin/dossiers/' . $this->bien->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sla_depasse',
            'sla_type' => $this->typeSla,
            'bien_id' => $this->bien->id,
            'message' => $this->message
        ];
    }
}
