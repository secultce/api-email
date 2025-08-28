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
        Schema::dropIfExists('email_audits');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::create('email_audits', function (Blueprint $table) {
            $table->id();
            // Adicione aqui as colunas que existiam na tabela original, caso precise reverter
            $table->timestamps();
        });
    }
};
