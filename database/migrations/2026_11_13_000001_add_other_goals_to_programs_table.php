<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Autres objectifs du questionnaire (deux au plus, en plus de l'objectif principal) : vide pour les programmes d'avant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->json('other_goals')->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('other_goals');
        });
    }
};
