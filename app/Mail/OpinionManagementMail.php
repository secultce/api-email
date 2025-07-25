<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpinionManagementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(protected $data)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Justificativa da Análise de Pareceristas Disponível',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.opinion-management',
            with: [
                'number' => $this->data['number'],
                'agent' => $this->data['agent']['name'],
                'opportunity' => $this->data['opportunity'],
                'link' => $this->data['url'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
