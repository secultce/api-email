<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_dispatches', function (Blueprint $table) {
            $table->string('status', 30)->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_dispatches', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'attempted_at',
                'failed_at',
                'error_message',
            ]);
        });
    }
};
