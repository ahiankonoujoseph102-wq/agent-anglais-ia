<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Compte de test » (fiche d'un inscrit) : un tel compte passe la nouvelle évaluation quand l'interrupteur des
 * Réglages vaut « Comptes de test seulement » (App\Support\Live\AssessmentVersion). Aucun compte n'en est un au départ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_test_account')->default(false)->after('welcomed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_test_account');
        });
    }
};
