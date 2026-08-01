<?php

namespace App\Notifications;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class CreneauxProposesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $visite;

    /**
     * Create a new notification instance.
     */
    public function __construct(Visite $visite)
    {
        $this->visite = $visite;
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
            ->subject('📅 Choisissez un créneau de visite — ' . $this->visite->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Notre agent souhaite planifier une visite de vérification pour votre bien ' . $this->visite->bien->titre . '.')
            ->line('Voici les créneaux proposés :');

        $creneaux = is_array($this->visite->creneaux_proposes) 
            ? $this->visite->creneaux_proposes 
            : json_decode($this->visite->creneaux_proposes, true);

        if ($creneaux) {
            foreach ($creneaux as $index => $c) {
                $dateStr = Carbon::parse($c['date'])->translatedFormat('l d F Y');
                $heure = $c['heure_debut'];
                $mail->line('- ' . $dateStr . ' à ' . $heure);
            }
        }

        $mail->action('Choisir un créneau', url('/client/visites/' . $this->visite->id . '/choisir-creneau'))
            ->line('Si aucun de ces créneaux ne vous convient, vous pourrez le signaler sur la page.');

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
            'type' => 'creneaux_proposes',
            'visite_id' => $this->visite->id,
            'bien_id' => $this->visite->bien_id,
            'message' => 'L\'agent vous propose des créneaux pour la visite du bien ' . $this->visite->bien->titre . '.'
        ];
    }
}
