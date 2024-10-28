<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use JuniorShyko\Phpextensive\Extensive;

class DeadlineForAccountability extends Mailable
{
    use Queueable, SerializesModels;
    public string $complementText = 'está na hora de preencher e enviar';
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
        $e = new  Extensive();
        if($this->info->days_current === 85){
            $this->complementText = 'faltam 05 (cinco) dias para o envio';
        }
        return new Content(
            view: 'emails.deadline-for-accountability',
            with: [
                'info' => $this->info,
                'days_current' => $e->extensive( $this->info->days_current, Extensive::MALE_NUMBER ),
                'complment_text' => $this->complementText,
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
