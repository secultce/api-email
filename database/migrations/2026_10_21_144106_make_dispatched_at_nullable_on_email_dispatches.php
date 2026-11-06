<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_dispatches', function (Blueprint $table) {
            $table->timestamp('dispatched_at')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        /*
         * Não restauramos a obrigatoriedade automaticamente:
         * registros criados após a migration podem ter NULL.
         *
         * Antes de reverter, seria necessário definir uma
         * estratégia para esses registros.
         */
        throw new RuntimeException('Reversão manual necessária para dispatched_at.');
    }
};
