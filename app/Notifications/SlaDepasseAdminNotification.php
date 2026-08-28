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

    public readonly string $messageText;

    public function __construct(
        public readonly Bien   $bien,
        public readonly string $typeSla,  // 'sla1' | 'sla2'
    ) {
        $this->messageText = $typeSla === 'sla1'
            ? "Alerte SLA 1 : Le bien « {$bien->titre} » est en attente d'agent depuis trop longtemps."
            : "Alerte SLA 2 : Le bien « {$bien->titre} » n'a pas eu d'évolution depuis trop longtemps.";
    }

    /**
     * La persistance DB est gérée par NotificationService.
     * Ici on envoie uniquement l'email.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $prenom = $notifiable->first_name ?? $notifiable->name ?? '';

        return (new MailMessage)
            ->subject('Alerte SLA Dépassé — ' . $this->bien->titre)
            ->greeting("Bonjour {$prenom},")
            ->line($this->messageText)
            ->action('Voir le dossier', url('/admin/dossiers/' . $this->bien->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'sla_depasse',
            'sla_type' => $this->typeSla,
            'bien_id'  => $this->bien->id,
            'message'  => $this->messageText,
        ];
    }
}
