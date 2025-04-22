<?php

namespace Tests\Unit\Mail;

use App\Mail\PublishedRecourse;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Tests\TestCase;

class PublishedRecourseTest extends TestCase
{
    public function test_it_constructs_with_data_array(): void
    {
        $data = [
            'opportunityName' => 'Test Opportunity',
            'agentId' => '12345',
        ];

        $mail = new PublishedRecourse($data);

        $this->assertInstanceOf(PublishedRecourse::class, $mail);
    }

    public function test_it_sets_correct_envelope_subject(): void
    {
        $data = [
            'opportunityName' => 'Test Opportunity',
            'agentId' => '12345',
        ];

        $mail = new PublishedRecourse($data);
        $envelope = $mail->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertEquals('Resposta do recurso publicada', $envelope->subject);
    }

    public function test_it_uses_correct_view_template(): void
    {
        $data = [
            'opportunityName' => 'Test Opportunity',
            'agentId' => '12345',
        ];

        $mail = new PublishedRecourse($data);
        $content = $mail->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertEquals('emails.published-recourse', $content->view);
    }

    public function test_it_passes_correct_data_to_view(): void
    {
        $opportunityName = 'Test Opportunity';
        $agentId = '12345';

        $data = [
            'opportunityName' => $opportunityName,
            'agentId' => $agentId,
        ];

        $mail = new PublishedRecourse($data);
        $content = $mail->content();

        $this->assertEquals([
            'opportunityName' => $opportunityName,
            'agentId' => $agentId,
        ], $content->with);
    }

    public function test_it_has_no_attachments(): void
    {
        $data = [
            'opportunityName' => 'Test Opportunity',
            'agentId' => '12345',
        ];

        $mail = new PublishedRecourse($data);

        $this->assertEmpty($mail->attachments());
    }
}
