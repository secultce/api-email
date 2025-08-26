<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_audits', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('subject');
            $table->text('body');
            $table->string('action');
            $table->string('entity');
            $table->unsignedInteger('agent_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_audits');
    }
};