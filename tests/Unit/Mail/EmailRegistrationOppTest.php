<?php

namespace Tests\Unit\Mail;

use App\Mail\EmailRegistrationOpp;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Tests\TestCase;

class EmailRegistrationOppTest extends TestCase
{
    public function test_it_constructs_with_required_parameters(): void
    {
        $nameUser = 'John Doe';
        $number = '12345';
        $days = '30';

        $mail = new EmailRegistrationOpp(
            nameUser: $nameUser,
            number: $number,
            days: $days,
        );

        $this->assertEquals($nameUser, $mail->nameUser);
        $this->assertEquals($number, $mail->number);
        $this->assertEquals($days, $mail->days);
    }

    public function test_it_sets_correct_envelope_subject(): void
    {
        $mail = new EmailRegistrationOpp(
            nameUser: 'John Doe',
            number: '12345',
            days: '30',
        );

        $envelope = $mail->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertEquals('Diligência Aberta - Resposta Necessária', $envelope->subject);
    }

    public function test_it_uses_correct_view_template(): void
    {
        $mail = new EmailRegistrationOpp(
            nameUser: 'John Doe',
            number: '12345',
            days: '30',
        );

        $content = $mail->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertEquals('emails.registration', $content->view);
    }

    public function test_it_passes_properties_to_view(): void
    {
        $nameUser = 'John Doe';
        $number = '12345';
        $days = '30';

        $mail = new EmailRegistrationOpp(
            nameUser: $nameUser,
            number: $number,
            days: $days,
        );

        // This test confirms that the properties are accessible to the view
        // by checking that they're public properties on the mailable
        $this->assertTrue(property_exists($mail, 'nameUser'));
        $this->assertTrue(property_exists($mail, 'number'));
        $this->assertTrue(property_exists($mail, 'days'));

        $this->assertEquals($nameUser, $mail->nameUser);
        $this->assertEquals($number, $mail->number);
        $this->assertEquals($days, $mail->days);
    }

    public function test_it_has_no_attachments(): void
    {
        $mail = new EmailRegistrationOpp(
            nameUser: 'John Doe',
            number: '12345',
            days: '30',
        );

        $this->assertEmpty($mail->attachments());
    }
}
