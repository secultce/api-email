<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_dispatches', function (Blueprint $table) {
            $table->string('idempotency_key', 191)
                ->nullable()
                ->unique();

            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->unsignedInteger('attempts')
                ->default(0);

            $table->text('last_error')
                ->nullable();

            $table->timestamp('sent_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_dispatches', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'idempotency_key',
                'status',
                'attempts',
                'last_error',
                'sent_at',
            ]);
        });
    }
};
