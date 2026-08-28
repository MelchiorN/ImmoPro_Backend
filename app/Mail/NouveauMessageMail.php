<?php

namespace App\Mail;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de notification d'un nouveau message.
 *
 * Règle : on n'inclut PAS le contenu du message dans l'email.
 * On indique uniquement qu'un message a été reçu, qui l'a envoyé
 * et sur quel bien la conversation porte.
 */
class NouveauMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User         $destinataire,
        public readonly User         $expediteur,
        public readonly Conversation $conversation,
    ) {}

    public function envelope(): Envelope
    {
        $expediteurNom = trim(
            ($this->expediteur->first_name ?? '') . ' ' .
            ($this->expediteur->last_name  ?? '')
        ) ?: 'Un client';

        return new Envelope(
            subject: "Nouveau message de {$expediteurNom} — ImmoPro",
        );
    }

    public function content(): Content
    {
        $destinatairePrenom = $this->destinataire->first_name ?? 'Cher agent';
        $expediteurNom = trim(
            ($this->expediteur->first_name ?? '') . ' ' .
            ($this->expediteur->last_name  ?? '')
        ) ?: 'Un client';

        $bienTitre = $this->conversation->bien?->titre;

        return new Content(
            view: 'emails.nouveau-message',
            with: [
                'destinatairePrenom' => $destinatairePrenom,
                'expediteurNom'      => $expediteurNom,
                'bienTitre'          => $bienTitre,
                'appUrl'             => config('app.url'),
            ],
        );
    }
}
