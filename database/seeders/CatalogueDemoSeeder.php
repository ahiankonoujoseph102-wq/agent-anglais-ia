<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\PrivateStorage;
use App\Support\Videos\SiteVideos;
use App\Support\Videos\VideoTopics;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Base de DÉMONSTRATION du catalogue des écrans (tools/presentation/catalogue.mjs) : celle des vidéos de présentation,
 * plus un administrateur fictif, trois vidéos de leçon et les vidéos de la page « Comment ça marche ». Jamais en
 * production. AUCUN paiement : le compte « pending » perd son paiement de démonstration (aucun compteur de paiements,
 * aucun montant encaissé ne peut apparaître), et rien n'est jamais envoyé.
 *
 * Images de Teacher Joe : storage/framework/catalogue/teacher.mp4 et poster.jpg, écrits par catalogue.mjs avant la
 * base (sinon une image unie et une vidéo vide, pour les tests).
 *
 * php artisan db:seed --class=CatalogueDemoSeeder
 */
class CatalogueDemoSeeder extends Seeder
{
    /** Numéro fictif de l'administrateur de démonstration (même plage que les comptes « Élève »). */
    public const ADMIN_PHONE = '+22899000009';

    /** Vidéos de leçon montrées dans la page « Vidéos » de l'administration. */
    public const LESSON_VIDEOS = 3;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Base de démonstration refusée en production.');

            return;
        }

        $this->call(PresentationDemoSeeder::class);

        // Aucun paiement dans le catalogue, même de démonstration.
        $pending = User::query()->where('phone', PresentationDemoSeeder::ACCOUNTS['pending'])->first();
        $pending?->payments()->delete();
        $pending?->subscriptions()->delete();

        // L'attestation du compte « active » est récente : « Mon espace » la fête et la range dans « Mes attestations ».
        User::query()->where('phone', PresentationDemoSeeder::ACCOUNTS['active'])->first()
            ?->attestations()->update(['issued_at' => now()->subDays(2)]);

        $admin = User::factory()->create(['name' => 'Administrateur', 'phone' => self::ADMIN_PHONE, 'password' => Hash::make(PresentationDemoSeeder::PASSWORD), 'welcomed_at' => now()]);
        $admin->role = Role::Admin;
        $admin->save();

        $media = $this->media();
        $this->lessonVideos($media);
        $this->presentationVideos($media);
    }

    /** @return array{video: string, poster: string} */
    private function media(): array
    {
        $folder = storage_path('framework/catalogue');
        $video = is_file("{$folder}/teacher.mp4") ? (string) file_get_contents("{$folder}/teacher.mp4") : '';
        $poster = is_file("{$folder}/poster.jpg") ? (string) file_get_contents("{$folder}/poster.jpg") : self::plainJpeg();

        return ['video' => $video, 'poster' => $poster];
    }

    /** @param array{video: string, poster: string} $media */
    private function lessonVideos(array $media): void
    {
        $disk = PrivateStorage::disk();

        foreach (array_slice(VideoTopics::all(), 0, self::LESSON_VIDEOS) as $index => $topic) {
            $video = LessonVideo::query()->create([
                'topic_key' => $topic['key'], 'day' => today(), 'slot' => $index + 1, 'level' => $topic['level'], 'title' => $topic['title'],
                'status' => LessonVideo::READY, 'duration_seconds' => 118, 'lips_correlation' => 0.86, 'loudness_lufs' => -14.1, 'true_peak_db' => -1.2,
                'caption' => "{$topic['title']} : la leçon du jour avec ".config('avatar.model.name').". Évaluation orale gratuite sur talkami.laravel.cloud\n#anglais #apprendrelanglais",
                'ready_at' => now()->subHours(3 - $index),
            ]);
            $path = "videos/{$video->day->format('Y-m-d')}/{$video->id}/".$video->downloadName();
            $disk->put($path, $media['video']);
            $video->update(['video_path' => $path, 'size_bytes' => strlen($media['video'])]);
        }
    }

    /**
     * Vidéos de la page « Comment ça marche » (et la ligne sous les formules), avec leur version « site ».
     *
     * @param  array{video: string, poster: string}  $media
     */
    private function presentationVideos(array $media): void
    {
        $disk = PrivateStorage::disk();
        $titles = ['evaluation' => 'Votre évaluation orale gratuite', 'talkami' => config('platform.name').' en 5 minutes', 'questions' => 'Vos questions, mes réponses'];

        foreach ($titles as $key => $title) {
            $video = PresentationVideo::query()->create(['key' => $key, 'title' => $title, 'status' => LessonVideo::READY, 'duration_seconds' => 20, 'ready_at' => now()]);
            $light = $video->folder().'/'.$video->downloadName(true);
            $disk->put($light, $media['video']);
            $disk->put(SiteVideos::path($video, 'video'), $media['video']);
            $disk->put(SiteVideos::path($video, 'apercu'), $media['poster']);
            $video->update([
                'video_path' => $light, 'light_path' => $light,
                'web_path' => SiteVideos::path($video, 'video'), 'poster_path' => SiteVideos::path($video, 'apercu'),
                'web_size_bytes' => max(3 * 1048576, strlen($media['video'])),
            ]);
        }
    }

    /** Image unie bleu nuit (tests, ou sans image de Teacher Joe). */
    private static function plainJpeg(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return '';
        }

        $image = imagecreatetruecolor(480, 854);
        imagefill($image, 0, 0, imagecolorallocate($image, 14, 42, 92));
        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }
}
