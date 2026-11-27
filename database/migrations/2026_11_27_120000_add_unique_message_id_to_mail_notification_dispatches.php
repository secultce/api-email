<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Garante uma única notificação de inscrição por message_id.
     *
     * O índice é parcial para não afetar os demais mailables, cujos
     * retries podem registrar mais de um dispatch com o mesmo message_id.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uq_email_dispatches_mail_notification_message_id
            ON email_dispatches ((meta->>'message_id'))
            WHERE mailable_type = 'App\Mail\MailNotification'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_email_dispatches_mail_notification_message_id');
    }
};
