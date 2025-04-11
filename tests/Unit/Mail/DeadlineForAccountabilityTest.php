<?php

namespace Tests\Unit\Mail;

use App\Mail\DeadlineForAccountability;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use JuniorShyko\Phpextensive\Extensive;
use Tests\TestCase;

class DeadlineForAccountabilityTest extends TestCase
{
    public function test_it_constructs_with_info_object(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);

        $this->assertInstanceOf(DeadlineForAccountability::class, $mail);
    }

    public function test_it_sets_correct_envelope_subject_for_refo(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);
        $envelope = $mail->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertEquals('Mapa Cultural - Prazo para envio da prestação de contas (REFO)', $envelope->subject);
    }

    public function test_it_sets_correct_envelope_subject_for_raio(): void
    {
        $info = (object) [
            'notification_type' => 'RAIO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);
        $envelope = $mail->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertEquals('Mapa Cultural - Prazo para envio da prestação de contas (RAIO)', $envelope->subject);
    }

    public function test_it_sets_default_complement_text_when_days_current_is_not_85_or_55(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);

        // Trigger content method to set complementText
        $mail->content();

        $this->assertEquals('está na hora de preencher e enviar', $mail->complementText);
    }

    public function test_it_sets_special_complement_text_when_days_current_is_85(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 85,
        ];

        $mail = new DeadlineForAccountability($info);

        // Trigger content method to set complementText
        $mail->content();

        $this->assertEquals('faltam 05 (cinco) dias para o envio', $mail->complementText);
    }

    public function test_it_sets_special_complement_text_when_days_current_is_55(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 55,
        ];

        $mail = new DeadlineForAccountability($info);

        // Trigger content method to set complementText
        $mail->content();

        $this->assertEquals('faltam 05 (cinco) dias para o envio', $mail->complementText);
    }

    public function test_it_sets_correct_title_report_for_refo(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);

        // Trigger content method to set titleReport
        $mail->content();

        $this->assertEquals('Relatório de Execução Final do Objeto - REFO', $mail->titleReport);
    }

    public function test_it_sets_correct_title_report_for_raio(): void
    {
        $info = (object) [
            'notification_type' => 'RAIO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);

        // Trigger content method to set titleReport
        $mail->content();

        $this->assertEquals('Relatório de Avaliação Intermediária do Objeto - RAIO', $mail->titleReport);
    }

    public function test_it_returns_correct_content_view(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);
        $content = $mail->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertEquals('emails.deadline-for-accountability', $content->view);
    }

    public function test_it_passes_correct_data_to_view(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);
        $content = $mail->content();

        $extensive = new Extensive;
        $expectedDaysCurrent = $extensive->extensive(90, Extensive::MALE_NUMBER);

        $viewData = $content->with;
        $this->assertSame($info, $viewData['info']);
        $this->assertEquals($expectedDaysCurrent, $viewData['days_current']);
        $this->assertEquals('está na hora de preencher e enviar', $viewData['complement_text']);
        $this->assertEquals('Relatório de Execução Final do Objeto - REFO', $viewData['title_report']);
    }

    public function test_it_has_no_attachments(): void
    {
        $info = (object) [
            'notification_type' => 'REFO',
            'days_current' => 90,
        ];

        $mail = new DeadlineForAccountability($info);

        $this->assertEmpty($mail->attachments());
    }
}
