<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DecisionBienNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Bien    $bien,
        public readonly string  $decision,  // 'approuve' | 'rejete'
        public readonly ?string $motif = null,
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
        $prenom = $notifiable->first_name ?? $notifiable->name ?? '';

        if ($this->decision === 'approuve') {
            return (new MailMessage)
                ->subject('Votre bien est publié — ' . $this->bien->titre)
                ->greeting("Bonjour {$prenom},")
                ->line('Bonne nouvelle ! Votre bien « ' . $this->bien->titre . ' » a été approuvé et est maintenant publié sur la plateforme.')
                ->action('Voir mon annonce', url('/biens/' . $this->bien->id));
        }

        return (new MailMessage)
            ->subject('Votre bien a été rejeté — ' . $this->bien->titre)
            ->greeting("Bonjour {$prenom},")
            ->line('Après vérification, votre bien « ' . $this->bien->titre . ' » ne peut pas être publié.')
            ->line('Motif : ' . ($this->motif ?? '—'))
            ->action('Voir mes annonces', url('/profile/annonces'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'decision_bien',
            'decision' => $this->decision,
            'bien_id'  => $this->bien->id,
        ];
    }
}
