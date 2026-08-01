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

    public $bien;
    public $decision; // 'approuve' ou 'rejete'
    public $motif;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien, string $decision, ?string $motif = null)
    {
        $this->bien = $bien;
        $this->decision = $decision;
        $this->motif = $motif;
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
        if ($this->decision === 'approuve') {
            return (new MailMessage)
                ->subject('✅ Votre bien est publié — ' . $this->bien->titre)
                ->greeting('Bonjour ' . $notifiable->name . ',')
                ->line('Bonne nouvelle ! Votre bien ' . $this->bien->titre . ' a été approuvé et est maintenant publié sur notre plateforme.')
                ->action('Voir mon annonce', url('/biens/' . $this->bien->id));
        } else {
            return (new MailMessage)
                ->subject('❌ Votre bien a été rejeté — ' . $this->bien->titre)
                ->greeting('Bonjour ' . $notifiable->name . ',')
                ->line('Après vérification, votre bien ' . $this->bien->titre . ' ne peut pas être publié.')
                ->line('Motif : ' . $this->motif)
                ->action('Voir mes annonces', url('/profile/annonces'));
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $msg = $this->decision === 'approuve' 
            ? '✅ Bonne nouvelle ! Votre bien ' . $this->bien->titre . ' a été approuvé et publié.'
            : '❌ Votre bien ' . $this->bien->titre . ' a été rejeté. Motif: ' . $this->motif;

        return [
            'type' => 'decision_bien',
            'decision' => $this->decision,
            'bien_id' => $this->bien->id,
            'message' => $msg
        ];
    }
}
