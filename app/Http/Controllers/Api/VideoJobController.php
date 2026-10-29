<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Support\PrivateStorage;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fabrication des vidéos de leçon par GitHub Actions (jeton secret, middleware video.token) : travaux à faire,
 * voix à télécharger, dépôt de la vidéo, résultat.
 */
class VideoJobController extends Controller
{
    public function jobs(Request $request, LessonVideos $videos): JsonResponse
    {
        // « apercu » : combien attendent, sans rien prendre (GitHub n'installe rien s'il n'y a rien à faire).
        $blocked = VideoSettings::blocked();

        // « type » : club ou promo (le Club a son propre passage de GitHub, avec plus de temps).
        $kind = in_array($request->query('type'), [LessonVideo::CLUB, LessonVideo::PROMO], true) ? (string) $request->query('type') : null;

        if ($request->boolean('apercu')) {
            return response()->json(['waiting' => $videos->waiting($kind), 'blocked' => $blocked]);
        }

        return response()->json(['jobs' => $videos->claim((int) $request->integer('limit', 1), $kind), 'blocked' => $blocked]);
    }

    public function audio(LessonVideo $video): StreamedResponse|JsonResponse
    {
        $disk = PrivateStorage::disk();

        if ($video->audio_path === null || ! $disk->exists($video->audio_path)) {
            return response()->json(['message' => 'Voix introuvable.'], 404);
        }

        return $disk->download($video->audio_path, 'voice.wav', ['Content-Type' => 'audio/wav', 'Cache-Control' => 'no-store']);
    }

    /** Dépôt par l'API quand le disque ne donne pas d'adresse signée (développement, autre disque). */
    public function upload(Request $request, LessonVideo $video): JsonResponse
    {
        if ($video->status !== LessonVideo::RENDERING) {
            return response()->json(['message' => 'Cette vidéo n\'est pas en fabrication.'], 409);
        }

        $max = (int) config('videos.max_megabytes') * 1024 * 1024;
        $length = (int) $request->header('Content-Length', '0');

        if ($length <= 0 || $length > $max) {
            return response()->json(['message' => 'Vidéo vide ou trop lourde.'], 413);
        }

        $stream = $request->getContent(true);
        PrivateStorage::disk()->writeStream(LessonVideos::videoPath($video), $stream);

        return response()->json(['stored' => true]);
    }

    public function done(Request $request, LessonVideo $video, LessonVideos $videos): JsonResponse
    {
        $data = $request->validate([
            'duration' => ['nullable', 'numeric', 'min:1', 'max:900'],
            'lips_correlation' => ['nullable', 'numeric', 'between:-1,1'],
            'lips_open' => ['nullable', 'numeric', 'between:0,5'],
            'loudness_lufs' => ['nullable', 'numeric', 'between:-70,0'],
            'true_peak_db' => ['nullable', 'numeric', 'between:-70,6'],
        ]);

        if ($video->status !== LessonVideo::RENDERING) {
            return response()->json(['message' => 'Cette vidéo n\'est pas en fabrication.'], 409);
        }

        $measures = array_map(fn ($value) => $value !== null ? (float) $value : null, array_diff_key($data, ['duration' => true]));

        if (! $videos->markReady($video, isset($data['duration']) ? (float) $data['duration'] : null, $measures)) {
            return response()->json(['message' => 'Vidéo introuvable dans le stockage.'], 422);
        }

        return response()->json(['ready' => true]);
    }

    public function failed(Request $request, LessonVideo $video, LessonVideos $videos): JsonResponse
    {
        $data = $request->validate(['error' => ['nullable', 'string', 'max:300']]);

        if ($video->status === LessonVideo::RENDERING) {
            $videos->markRenderFailed($video, $data['error'] ?? 'erreur inconnue');
        }

        return response()->json(['recorded' => true]);
    }

    /** Machine de GitHub trop lente : la vidéo est rendue, sans échec compté. */
    public function release(LessonVideo $video, LessonVideos $videos): JsonResponse
    {
        if ($video->status === LessonVideo::RENDERING) {
            $videos->release($video);
        }

        return response()->json(['released' => true]);
    }
}
