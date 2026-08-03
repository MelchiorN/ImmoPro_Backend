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

    public function __construct(public readonly Bien $bien) {}

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
        return (new MailMessage)
            ->subject('📂 Nouveau dossier à traiter — ' . $this->bien->titre)
            ->greeting('Bonjour ' . ($notifiable->first_name ?? $notifiable->name ?? '') . ',')
            ->line('Un nouveau bien de catégorie ' . $this->bien->type_bien . ' a été soumis à ' . $this->bien->adresse . '.')
            ->action('Prendre en charge', url('/agent/biens/' . $this->bien->id))
            ->line('Connectez-vous pour le prendre en charge.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'nouveau_dossier',
            'bien_id' => $this->bien->id,
            'message' => 'Nouveau dossier soumis : ' . $this->bien->titre,
        ];
    }
}
