<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adresse e-mail officielle : contact@talkami.net (valeur par défaut de config/platform.php). Une ancienne adresse
 * Gmail saisie dans les « Réglages » la remplacerait encore sur le site : elle est retirée, pour que la valeur par
 * défaut s'applique. Toute autre adresse choisie par l'administrateur n'est jamais touchée.
 */
return new class extends Migration
{
    private const OLD = ['ahiankonoujoseph102@gmail.com', 'amitalk62@gmail.com'];

    public function up(): void
    {
        DB::table('settings')->where('key', 'contact.email')->whereIn('value', self::OLD)->delete();
    }

    public function down(): void
    {
        // Rien à remettre : sans réglage, le site affiche la valeur par défaut de la configuration.
    }
};
