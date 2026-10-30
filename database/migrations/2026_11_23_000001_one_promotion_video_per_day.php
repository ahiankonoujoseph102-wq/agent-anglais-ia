<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Décision du fondateur (30 octobre 2026) : UNE vidéo de promotion par jour (niveau A2), plus la vidéo du Club. Le
 * réglage « Vidéos de promotion par jour » de la page « Vidéos », s'il a été enregistré, repasse à 1 (sans réglage, la
 * configuration vaut déjà 1). Aucune vidéo n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'videos.per_day')->update(['value' => '1']);
    }

    public function down(): void
    {
        // Rien à défaire : le réglage se change dans la page « Vidéos ».
    }
};
