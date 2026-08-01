<?php

namespace App\Notifications;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class VisiteConfirmeeAgentNotification extends Notification implements ShouldQueue
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
        $dateStr = Carbon::parse($this->visite->date_visite)->translatedFormat('l d F Y à H:i');
        
        return (new MailMessage)
            ->subject('✅ Visite confirmée — ' . $this->visite->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Le déposant a confirmé la visite de vérification pour le bien ' . $this->visite->bien->titre . '.')
            ->line('Date : ' . $dateStr)
            ->action('Voir la visite', url('/agent/visites/' . $this->visite->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $dateStr = Carbon::parse($this->visite->date_visite)->translatedFormat('d/m/Y à H:i');
        return [
            'type' => 'visite_confirmee',
            'visite_id' => $this->visite->id,
            'bien_id' => $this->visite->bien_id,
            'message' => 'Le déposant a confirmé la visite du bien ' . $this->visite->bien->titre . ' pour le ' . $dateStr . '.'
        ];
    }
}
