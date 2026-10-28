<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme d'ambassadeurs : un compte = au plus un ambassadeur (code personnel unique), et chaque mois gratuit
 * offert est un accès offert ordinaire (subscriptions) relié à l'ambassadeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('status', 20)->default('active')->index();
            // Mois gratuits déjà ouverts (6 au plus) ; gardés si l'ambassadeur est retiré puis réintégré.
            $table->unsignedTinyInteger('months_granted')->default(0);
            // Moins de 18 ans : accord écrit d'un parent reçu (date), noté par l'administrateur.
            $table->timestamp('parental_consent_at')->nullable();
            $table->foreignId('appointed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('ambassador_id')->nullable()->after('granted_days')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ambassador_id');
        });

        Schema::dropIfExists('ambassadors');
    }
};
