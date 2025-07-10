<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublishedOpinions extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected array $data,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pareceres da inscrição publicados',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.published-opinions',
            with: [
                'opportunity' => $this->data['opportunity'],
                'registration' => $this->data['registration'],
            ],
        );
    }
}
