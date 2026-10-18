<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesure des tours de parole (jamais l'audio) : fin réelle de chaque réplique et mesures du tour (début et fin de
 * la parole selon le micro et selon le fournisseur, fin du tour, premier son du professeur, délai de réponse),
 * et événements de la séance (reconnexion, erreur, micro fermé, pause…). Effacés avec le texte des conversations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_lines', function (Blueprint $table) {
            $table->dateTime('ended_at')->nullable()->after('spoken_at');
            $table->json('metrics')->nullable()->after('ended_at');
        });

        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_type', 20);
            $table->unsignedBigInteger('conversation_id');
            $table->string('kind', 30);
            $table->string('detail', 200)->nullable();
            $table->dateTime('happened_at');
            $table->timestamps();

            $table->index(['conversation_type', 'conversation_id'], 'conversation_events_conversation_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_events');

        Schema::table('conversation_lines', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'metrics']);
        });
    }
};
