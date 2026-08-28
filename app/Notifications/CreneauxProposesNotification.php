<?php

namespace App\Notifications;

use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreneauxProposesNotification extends Notification implements ShouldQueue
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
        $prenom     = $notifiable->first_name ?? $notifiable->name ?? '';
        $titreBien  = $this->visite->bien?->titre ?? '';

        $mail = (new MailMessage)
            ->subject('Choisissez un créneau de visite — ' . $titreBien)
            ->greeting("Bonjour {$prenom},")
            ->line("Notre agent souhaite planifier une visite de vérification pour votre bien « {$titreBien} ».")
            ->line('Voici les créneaux proposés :');

        $creneaux = is_array($this->visite->creneaux_proposes)
            ? $this->visite->creneaux_proposes
            : json_decode($this->visite->creneaux_proposes ?? '[]', true);

        foreach (($creneaux ?? []) as $c) {
            $dateStr = isset($c['date'])
                ? Carbon::parse($c['date'])->locale('fr')->isoFormat('dddd D MMMM YYYY')
                : '—';
            $heure = $c['heure_debut'] ?? '';
            $mail->line("- {$dateStr} à {$heure}");
        }

        return $mail
            ->action('Choisir un créneau', url('/client/visites/' . $this->visite->id . '/choisir-creneau'))
            ->line("Si aucun de ces créneaux ne vous convient, vous pourrez le signaler sur la page.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'creneaux_proposes',
            'visite_id'=> $this->visite->id,
            'bien_id'  => $this->visite->bien_id,
        ];
    }
}
