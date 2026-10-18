<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texte des conversations orales (jamais l'audio) : une ligne par réplique, de l'apprenant ou de
 * Teacher Joe, dans l'ordre, avec son heure. Effacé automatiquement après platform.transcripts.keep_days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // assessment, lesson, course ou level_test (App\Support\Live\Transcripts::TYPES), et l'identifiant de la séance.
            $table->string('conversation_type', 20);
            $table->unsignedBigInteger('conversation_id');
            // Numéro de la réplique donné par le navigateur : un nouvel envoi de la même réplique la complète.
            $table->unsignedInteger('position');
            $table->string('speaker', 10);
            $table->text('text');
            $table->dateTime('spoken_at');
            $table->timestamps();

            $table->unique(['conversation_type', 'conversation_id', 'position']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_lines');
    }
};
