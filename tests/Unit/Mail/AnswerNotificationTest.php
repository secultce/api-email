<?php

namespace Tests\Unit\Mail;

use App\Mail\AnswerNotification;
use Tests\TestCase;

class AnswerNotificationTest extends TestCase
{
    public function test_it_constructs_with_a_number(): void
    {
        $number = '12345';
        $mail = new AnswerNotification($number);

        $this->assertEquals($number, $mail->number);
    }

    public function test_it_has_the_correct_subject(): void
    {
        $mail = new AnswerNotification('12345');
        $envelope = $mail->envelope();

        $this->assertEquals('Diligência Respondida', $envelope->subject);
    }

    public function test_it_uses_the_correct_view(): void
    {
        $mail = new AnswerNotification('12345');
        $content = $mail->content();

        $this->assertEquals('emails.answer', $content->view);
    }

    public function test_it_has_no_attachments(): void
    {
        $mail = new AnswerNotification('12345');

        $this->assertEmpty($mail->attachments());
    }

    public function test_it_passes_number_to_the_view(): void
    {
        $number = '12345';
        $mail = new AnswerNotification($number);

        $rendered = $mail->render();

        // This assumes your view includes the number somewhere
        // You may need to adjust this assertion based on your view's actual content
        $this->assertStringContainsString($number, $rendered);
    }
}
