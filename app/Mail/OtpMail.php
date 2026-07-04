<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Créer une nouvelle instance du mailable.
     *
     * @param string $otp    Le code OTP à 6 chiffres
     * @param int    $expiry Durée de validité en minutes
     */
    public function __construct(
        public readonly string $otp,
        public readonly int $expiry = 10,
    ) {}

    /**
     * Enveloppe (sujet) de l'email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre code de vérification ImmoPro',
        );
    }

    /**
     * Contenu de l'email.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }

    /**
     * Pièces jointes — aucune.
     */
    public function attachments(): array
    {
        return [];
    }
}
