<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lien du groupe WhatsApp des abonnés (« Réglages » → « Club des abonnés ») : valeur de départ donnée par le
 * fondateur, posée seulement si le réglage n'existe pas encore. Il se change ou se vide ensuite dans « Réglages ».
 */
return new class extends Migration
{
    private const KEY = 'club.whatsapp_link';

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => self::KEY,
            'value' => 'https://chat.whatsapp.com/LIwa0gAQrc4FwPbJBnhrlm',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
