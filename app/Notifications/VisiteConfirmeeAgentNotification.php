<?php

namespace App\Notifications;

use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisiteConfirmeeAgentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Visite $visite) {}

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
        $prenom  = $notifiable->first_name ?? $notifiable->name ?? '';
        $dateStr = $this->visite->date_visite
            ? Carbon::parse($this->visite->date_visite)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm')
            : '—';

        return (new MailMessage)
            ->subject('Visite confirmée — ' . ($this->visite->bien?->titre ?? ''))
            ->greeting("Bonjour {$prenom},")
            ->line('Le propriétaire a confirmé la visite de vérification pour le bien « ' . ($this->visite->bien?->titre ?? '') . ' ».')
            ->line('Date : ' . $dateStr)
            ->action('Voir la visite', url('/agent/visites/' . $this->visite->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'visite_confirmee',
            'visite_id'=> $this->visite->id,
            'bien_id'  => $this->visite->bien_id,
        ];
    }
}
