<?php

namespace App\Notifications;

use App\Models\Bien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouveauDossierNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $bien;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bien $bien)
    {
        $this->bien = $bien;
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
            ->subject('📂 Nouveau dossier à traiter — ' . $this->bien->titre)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un nouveau bien de catégorie ' . $this->bien->type_bien . ' a été soumis à ' . $this->bien->adresse . '.')
            ->action('Prendre en charge', url('/agent/biens/' . $this->bien->id))
            ->line('Connectez-vous pour le prendre en charge.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nouveau_dossier',
            'bien_id' => $this->bien->id,
            'message' => 'Nouveau dossier soumis : ' . $this->bien->titre . ' — ' . $this->bien->type_bien . ' à ' . $this->bien->adresse . '. Prenez-le en charge !'
        ];
    }
}
