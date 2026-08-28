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

    public function __construct(
        public readonly Visite  $visite,
        public readonly ?string $note = null,
    ) {}

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
        $prenom    = $notifiable->first_name ?? $notifiable->name ?? '';
        $titreBien = $this->visite->bien?->titre ?? '';

        $mail = (new MailMessage)
            ->subject('Créneaux refusés — ' . $titreBien)
            ->greeting("Bonjour {$prenom},")
            ->line("Le propriétaire a refusé tous les créneaux proposés pour le bien « {$titreBien} ».");

        if ($this->note) {
            $mail->line('Note du propriétaire : ' . $this->note);
        }

        return $mail
            ->line('Veuillez proposer de nouveaux créneaux.')
            ->action('Proposer nouveaux créneaux', url('/agent/visites/proposer/' . $this->visite->bien_id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'creneaux_refuses',
            'visite_id'=> $this->visite->id,
            'bien_id'  => $this->visite->bien_id,
        ];
    }
}
