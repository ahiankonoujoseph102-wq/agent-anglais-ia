<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des inscrits des ambassadeurs : une ligne par compte rattaché (fixé à l'inscription, corrigé seulement par
 * l'administrateur), avec la façon dont il a été rattaché et les signaux de triche. Jamais une adresse IP : seulement
 * son empreinte (avec la clé de l'application), pour comparer deux inscriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // code (tapé à l'inscription), link (lien ou code QR), admin (corrigé par l'administrateur).
            $table->string('source', 10);
            $table->json('flags')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['ambassador_id', 'created_at']);
        });

        Schema::table('ambassadors', function (Blueprint $table) {
            // Empreinte de la dernière adresse IP de l'ambassadeur connecté (signal « même réseau »).
            $table->string('last_ip_hash', 64)->nullable()->after('removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ambassadors', function (Blueprint $table) {
            $table->dropColumn('last_ip_hash');
        });

        Schema::dropIfExists('ambassador_referrals');
    }
};
