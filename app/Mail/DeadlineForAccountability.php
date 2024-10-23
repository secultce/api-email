<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Rmunate\Utilities\SpellNumber;

class DeadlineForAccountability extends Mailable
{
    use Queueable, SerializesModels;
    public $number;
    /**
     * Create a new message instance.
     */
    public function __construct(protected $info)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Prazo para envio da prestação de contas ({$this->info->notification_type})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $words = SpellNumber::integer(85)->toLetters(); // 'pt' para português

        dd($words);

        return new Content(
            view: 'emails.deadline-for-accountability',
            with: [
                'info' => $this->info,
                'days_current' => $day,
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
