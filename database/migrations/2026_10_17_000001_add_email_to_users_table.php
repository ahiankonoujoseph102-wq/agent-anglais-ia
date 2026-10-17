<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresse e-mail FACULTATIVE (aucun envoi pour l'instant) : la connexion reste
 * par numéro de téléphone. Enregistrée en minuscules (App\Models\User) ;
 * unique quand elle est remplie (plusieurs comptes peuvent ne pas en avoir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
