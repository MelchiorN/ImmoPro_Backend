<?php

namespace App\Notifications;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreneauxRefusesAgentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $visite;
    public $note;

    /**
     * Create a new notification instance.
     */
    public function __construct(Visite $visite, ?string $note = null)
    {
        $this->visite = $visite;
        $this->note = $note;
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
        $mail = (new MailMessage)
            ->subject('❌ Créneaux refusés — ' . $this->visite->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Le déposant a refusé tous les créneaux proposés pour le bien ' . $this->visite->bien->titre . '.');

        if ($this->note) {
            $mail->line('Note du déposant : ' . $this->note);
        }

        $mail->line('Veuillez proposer de nouveaux créneaux.')
             ->action('Proposer nouveaux créneaux', url('/agent/visites/proposer/' . $this->visite->bien_id));

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'creneaux_refuses',
            'visite_id' => $this->visite->id,
            'bien_id' => $this->visite->bien_id,
            'message' => 'Le déposant a refusé les créneaux proposés pour ' . $this->visite->bien->titre . '.' . ($this->note ? ' Note: ' . $this->note : '')
        ];
    }
}
