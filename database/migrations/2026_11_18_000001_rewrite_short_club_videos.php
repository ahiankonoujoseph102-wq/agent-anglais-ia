<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vidéos du Club d'avant la règle des 3 minutes (traduction de chaque phrase anglaise, son corrigé) : celles
 * d'aujourd'hui ou plus tard sont réécrites (nouveau script, nouvelle voix, nouvelle fabrication), à la demande du
 * propriétaire. Les vidéos des jours passés ne changent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_videos')
            ->where('kind', 'club')
            ->whereDate('day', '>=', now()->toDateString())
            ->whereNotIn('status', ['planned'])
            ->where(fn ($query) => $query->whereNull('duration_seconds')->orWhere('duration_seconds', '<', 180))
            ->update([
                'status' => 'planned',
                'script' => null,
                'audio_path' => null,
                'timings' => null,
                'attempts' => 0,
                'next_attempt_at' => null,
                'render_started_at' => null,
                'error' => null,
                'published_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Rien à défaire : les vidéos réécrites restent valables.
    }
};
