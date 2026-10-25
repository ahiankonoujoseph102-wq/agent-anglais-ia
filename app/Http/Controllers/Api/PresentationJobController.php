<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\PrivateStorage;
use App\Support\Videos\PresentationVideos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fabrication des vidéos de présentation par GitHub Actions (tools/presentation/make.mjs, jeton secret) : travaux,
 * voix, dépôt des deux fichiers (TikTok et WhatsApp), résultat.
 */
class PresentationJobController extends Controller
{
    public function jobs(Request $request, PresentationVideos $videos): JsonResponse
    {
        $keys = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('cles', '')))));

        if ($request->boolean('apercu')) {
            return response()->json(['waiting' => $videos->waiting($keys)]);
        }

        return response()->json(['jobs' => $videos->claim($keys)]);
    }

    public function audio(PresentationVideo $presentation): StreamedResponse|JsonResponse
    {
        $disk = PrivateStorage::disk();

        if ($presentation->audio_path === null || ! $disk->exists($presentation->audio_path)) {
            return response()->json(['message' => 'Voix introuvable.'], 404);
        }

        return $disk->download($presentation->audio_path, 'voice.wav', ['Content-Type' => 'audio/wav', 'Cache-Control' => 'no-store']);
    }

    /** Dépôt par l'API quand le disque ne donne pas d'adresse signée (développement, autre disque). */
    public function upload(Request $request, PresentationVideo $presentation, string $version): JsonResponse
    {
        if ($presentation->status !== LessonVideo::RENDERING) {
            return response()->json(['message' => 'Cette vidéo n\'est pas en fabrication.'], 409);
        }

        $length = (int) $request->header('Content-Length', '0');

        if ($length <= 0 || $length > (int) config('videos.presentations.max_megabytes') * 1024 * 1024) {
            return response()->json(['message' => 'Vidéo vide ou trop lourde.'], 413);
        }

        PrivateStorage::disk()->writeStream(PresentationVideos::path($presentation, $version === 'whatsapp'), $request->getContent(true));

        return response()->json(['stored' => true]);
    }

    public function done(Request $request, PresentationVideo $presentation, PresentationVideos $videos): JsonResponse
    {
        $data = $request->validate([
            'duration' => ['nullable', 'numeric', 'min:1', 'max:900'],
            'lips_correlation' => ['nullable', 'numeric', 'between:-1,1'],
            'lips_open' => ['nullable', 'numeric', 'between:0,5'],
            'loudness_lufs' => ['nullable', 'numeric', 'between:-70,0'],
            'true_peak_db' => ['nullable', 'numeric', 'between:-70,6'],
        ]);

        if ($presentation->status !== LessonVideo::RENDERING) {
            return response()->json(['message' => 'Cette vidéo n\'est pas en fabrication.'], 409);
        }

        $measures = array_map(fn ($value) => $value !== null ? (float) $value : null, array_diff_key($data, ['duration' => true]));

        if (! $videos->markReady($presentation, isset($data['duration']) ? (float) $data['duration'] : null, $measures)) {
            return response()->json(['message' => 'Fichiers introuvables dans le stockage.'], 422);
        }

        return response()->json(['ready' => true]);
    }

    public function failed(Request $request, PresentationVideo $presentation, PresentationVideos $videos): JsonResponse
    {
        $data = $request->validate(['error' => ['nullable', 'string', 'max:300']]);

        if ($presentation->status === LessonVideo::RENDERING) {
            $videos->markRenderFailed($presentation, $data['error'] ?? 'erreur inconnue');
        }

        return response()->json(['recorded' => true]);
    }
}
