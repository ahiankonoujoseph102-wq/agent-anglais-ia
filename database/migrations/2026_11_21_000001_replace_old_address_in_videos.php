<?php

use App\Support\Videos\OldAddress;
use App\Support\Videos\VideoSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ancienne adresse (talkami.laravel.cloud) encore présente après le changement de domaine : le réglage « Adresse du
 * site » des vidéos passe à l'adresse officielle (APP_URL : talkami.net), et les textes à copier (« caption ») des
 * vidéos de leçon, du Club et de présentation sont corrigés. Les scripts (sous-titres, voix) et les fichiers vidéo ne
 * sont JAMAIS touchés : une vidéo qui dit l'ancienne adresse est signalée « à refaire » dans la page « Vidéos ».
 */
return new class extends Migration
{
    public function up(): void
    {
        $primary = OldAddress::primary();

        if ($primary === null || OldAddress::hosts() === []) {
            return;
        }

        $site = DB::table('settings')->where('key', 'videos.site')->value('value');

        if (OldAddress::isOld(VideoSettings::normalizeSite(is_string($site) ? $site : null))) {
            DB::table('settings')->where('key', 'videos.site')->update(['value' => $primary]);
        }

        foreach (['lesson_videos', 'presentation_videos'] as $table) {
            foreach (OldAddress::hosts() as $host) {
                DB::table($table)->where('caption', 'like', '%'.$host.'%')->orderBy('id')->each(function ($row) use ($table) {
                    DB::table($table)->where('id', $row->id)->update(['caption' => OldAddress::replace($row->caption)]);
                });
            }
        }
    }

    public function down(): void
    {
        // Rien à remettre : l'ancienne adresse ne doit plus être dite ni affichée.
    }
};
