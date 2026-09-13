<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Attachment;

class ProposalPlatformMail extends Mailable
{
    use Queueable, SerializesModels;

    public $company;

    public function __construct($company)
    {
        $this->company = $company;
    }

    /**
     * Asunto del correo
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Digitalización de la gestión de equipos: La evolución de nuestro servicio para ' . $this->company->name
        );
    }

    /**
     * Contenido del correo (email corto)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.proposal_platform',
            with: [
                'company' => $this->company,
            ],
        );
    }

    /**
     * Adjuntos (PDF generado dinámicamente)
     */
    public function attachments(): array
    {
        // Generar PDF desde Blade
        $pdf = Pdf::loadView('emails.proposal_platform', [
            'company' => $this->company
        ])->setPaper('legal', 'portrait');

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                'Propuesta_Plataforma_Biomedica_' . str_replace(' ', '_', $this->company->name) . '.pdf'
            )->withMime('application/pdf')
        ];
    }
}
