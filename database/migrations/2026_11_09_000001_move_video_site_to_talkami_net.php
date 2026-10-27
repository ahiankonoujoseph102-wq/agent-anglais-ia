<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Changement de domaine : l'adresse dite et affichée à la fin des vidéos (réglage « videos.site » de la page
 * « Vidéos ») passe de l'ancienne adresse à talkami.net. Seulement si elle vaut exactement l'ancienne : une autre
 * adresse choisie par l'administrateur n'est jamais touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'videos.site')->where('value', 'talkami.laravel.cloud')->update(['value' => 'talkami.net']);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'videos.site')->where('value', 'talkami.net')->update(['value' => 'talkami.laravel.cloud']);
    }
};
