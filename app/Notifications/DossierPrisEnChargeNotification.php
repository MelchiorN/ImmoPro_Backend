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

    public $bien;
    public $agent;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien, User $agent)
    {
        $this->bien = $bien;
        $this->agent = $agent;
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
            ->subject('✅ Votre dossier est pris en charge — ' . $this->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Votre bien ' . $this->bien->titre . ' est maintenant pris en charge par notre agent ' . $this->agent->name . '.')
            ->action('Voir mon bien', url('/biens/' . $this->bien->id))
            ->line('Notre agent reviendra vers vous très prochainement.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dossier_pris_en_charge',
            'bien_id' => $this->bien->id,
            'message' => 'Votre bien ' . $this->bien->titre . ' est maintenant pris en charge par notre agent ' . $this->agent->name . '.'
        ];
    }
}
