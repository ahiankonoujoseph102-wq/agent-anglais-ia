<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil de l'apprenant (étudiant, élève, enseignant…), choisi dans le questionnaire, facultatif :
 * les programmes déjà construits n'en ont pas et restent tels quels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('profile', 20)->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('profile');
        });
    }
};
