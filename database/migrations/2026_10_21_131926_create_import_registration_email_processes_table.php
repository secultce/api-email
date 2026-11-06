<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_registration_email_processes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('opportunity_id');
            $table->unsignedBigInteger('registration_id');

            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->uuid('message_id')->nullable();
            $table->string('agent_email');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(
                ['opportunity_id', 'registration_id'],
                'uq_import_registration_opportunity'
            );

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'import_registration_email_processes'
        );
    }
};
