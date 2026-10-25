<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\PrivateStorage;
use App\Support\Videos\SiteVideos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Version « site » des vidéos de présentation (page « Comment ça marche ») par GitHub Actions
 * (tools/presentation/site-version.mjs, jeton secret) : travaux, lecture de la version WhatsApp, dépôt, résultat.
 */
class SiteVideoJobController extends Controller
{
    public function jobs(Request $request, SiteVideos $videos): JsonResponse
    {
        $keys = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('cles', '')))));
        $waiting = $videos->waiting($keys);

        if ($request->boolean('apercu')) {
            return response()->json(['waiting' => $waiting->count()]);
        }

        return response()->json(['jobs' => $waiting->map(fn (PresentationVideo $video) => $videos->job($video))->values()->all()]);
    }

    /** Lecture de la version WhatsApp quand le disque ne donne pas d'adresse signée (développement). */
    public function source(PresentationVideo $presentation): StreamedResponse|JsonResponse
    {
        $disk = PrivateStorage::disk();

        if ($presentation->light_path === null || ! $disk->exists($presentation->light_path)) {
            return response()->json(['message' => 'Version WhatsApp introuvable.'], 404);
        }

        return $disk->download($presentation->light_path, 'source.mp4', ['Content-Type' => 'video/mp4', 'Cache-Control' => 'no-store']);
    }

    /** Dépôt par l'API quand le disque ne donne pas d'adresse signée. */
    public function upload(Request $request, PresentationVideo $presentation, string $file): JsonResponse
    {
        if ($presentation->status !== LessonVideo::READY || ! in_array($presentation->key, SiteVideos::keys(), true)) {
            return response()->json(['message' => 'Cette vidéo n\'est pas prévue pour le site.'], 409);
        }

        $length = (int) $request->header('Content-Length', '0');

        if ($length <= 0 || $length > (int) config('videos.presentations.site.max_megabytes') * 1024 * 1024) {
            return response()->json(['message' => 'Fichier vide ou trop lourd.'], 413);
        }

        PrivateStorage::disk()->writeStream(SiteVideos::path($presentation, $file), $request->getContent(true));

        return response()->json(['stored' => true]);
    }

    public function done(PresentationVideo $presentation, SiteVideos $videos): JsonResponse
    {
        if (! in_array($presentation->key, SiteVideos::keys(), true)) {
            return response()->json(['message' => 'Cette vidéo n\'est pas prévue pour le site.'], 409);
        }

        if (! $videos->markReady($presentation)) {
            return response()->json(['message' => 'Fichiers introuvables dans le stockage.'], 422);
        }

        return response()->json(['ready' => true]);
    }
}
