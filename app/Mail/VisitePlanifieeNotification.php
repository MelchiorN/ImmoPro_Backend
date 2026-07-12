<?php

namespace App\Mail;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitePlanifieeNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Visite $visite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📅 Visite planifiée pour votre bien — ImmoPro',
        );
    }

    public function content(): Content
    {
        $visite = $this->visite;
        $bien   = $visite->bien;
        $agent  = $visite->agent;

        $dateVisite = $visite->date_visite
            ? \Carbon\Carbon::parse($visite->date_visite)
                ->locale('fr')
                ->isoFormat('dddd D MMMM YYYY [à] HH[h]mm')
            : '—';

        return new Content(
            view: 'emails.visite-planifiee',
            with: [
                'dateVisite'  => $dateVisite,
                'bienTitre'   => $bien?->titre ?? 'Votre bien',
                'bienAdresse' => $bien?->adresse ?? '',
                'agentNom'    => $agent
                    ? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''))
                    : 'Un agent ImmoPro',
                'notes'       => $visite->notes,
            ],
        );
    }
}
