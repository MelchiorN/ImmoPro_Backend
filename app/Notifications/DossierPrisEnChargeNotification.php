<?php

namespace App\Notifications;

use App\Models\Bien;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DossierPrisEnChargeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Bien $bien,
        public readonly User $agent,
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
        $prenom   = $notifiable->first_name ?? $notifiable->name ?? '';
        $nomAgent = trim(($this->agent->first_name ?? '') . ' ' . ($this->agent->last_name ?? ''))
                    ?: ($this->agent->name ?? 'un agent');

        return (new MailMessage)
            ->subject('Votre dossier est pris en charge — ' . $this->bien->titre)
            ->greeting("Bonjour {$prenom},")
            ->line('Votre bien « ' . $this->bien->titre . ' » est maintenant pris en charge par ' . $nomAgent . '.')
            ->action('Voir mon bien', url('/biens/' . $this->bien->id))
            ->line('Notre agent reviendra vers vous très prochainement.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'dossier_pris_en_charge',
            'bien_id' => $this->bien->id,
        ];
    }
}
