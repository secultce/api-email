<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use JuniorShyko\Phpextensive\Extensive;

class DeadlineForAccountability extends Mailable
{
    use Queueable, SerializesModels;

    public string $complementText = 'está na hora de preencher e enviar';

    public string $titleReport = '';

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
            subject: "Mapa Cultural - Prazo para envio da prestação de contas ({$this->info->notification_type})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $e = new Extensive;
        if ($this->info->days_current === 85 || $this->info->days_current === 55) {
            $this->complementText = 'faltam 05 (cinco) dias para o envio';
        }

        // Mudando o titulo do relatório
        $this->titleReport = $this->info->notification_type === 'REFO'
            ? 'Relatório de Execução Final do Objeto - REFO'
            : 'Relatório de Avaliação Intermediária do Objeto - RAIO';

        return new Content(
            view: 'emails.deadline-for-accountability',
            with: [
                'info' => $this->info,
                'days_current' => $e->extensive($this->info->days_current, Extensive::MALE_NUMBER),
                'complement_text' => $this->complementText,
                'title_report' => $this->titleReport,
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
