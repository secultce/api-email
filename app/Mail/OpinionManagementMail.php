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
            subject: 'Publicação do parecer de sua oportunidade',
        );
    }

    public function content(): Content
    {
//        dd($this->data);
        return new Content(
            view: 'emails.opinion-management',
            with: [
                'number' => $this->data['number'],
                'agent' => $this->data['agent'],
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
