<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PrivateStorage;
use App\Support\Videos\ScreenCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue des écrans fabriqué par GitHub Actions (tools/presentation/catalogue.mjs, jeton secret) : voix de
 * Teacher Joe, réglages de son image, dépôt des deux fichiers, enregistrement du catalogue.
 */
class CatalogueJobController extends Controller
{
    public function job(ScreenCatalogue $catalogue): JsonResponse
    {
        $voice = $catalogue->voice();

        if ($voice === null) {
            return response()->json(['message' => 'La vidéo de présentation « '.config('videos.catalogue.voice_key').' » n\'est pas prête : sa voix sert au passage où Teacher Joe parle.'], 409);
        }

        return response()->json(['voice' => $voice, 'upload' => $catalogue->uploadTargets(), 'done_url' => route('api.catalogue.done')] + ScreenCatalogue::render());
    }

    /** Dépôt par l'API quand le disque ne donne pas d'adresse signée (développement, autre disque). */
    public function upload(Request $request, string $file): JsonResponse
    {
        $length = (int) $request->header('Content-Length', '0');

        if ($length <= 0 || $length > (int) config('videos.catalogue.max_megabytes') * 1024 * 1024) {
            return response()->json(['message' => 'Fichier vide ou trop lourd.'], 413);
        }

        PrivateStorage::disk()->writeStream(ScreenCatalogue::path($file), $request->getContent(true));

        return response()->json(['stored' => true]);
    }

    public function done(Request $request, ScreenCatalogue $catalogue): JsonResponse
    {
        $data = $request->validate([
            'duration' => ['required', 'numeric', 'min:1', 'max:7200'],
            'voice_start' => ['nullable', 'integer', 'min:0'],
            'passages' => ['required', 'array', 'min:1', 'max:200'],
            'passages.*.number' => ['required', 'integer'],
            'passages.*.key' => ['required', 'string', 'max:80'],
            'passages.*.title' => ['required', 'string', 'max:300'],
            'passages.*.start' => ['required', 'numeric'],
            'passages.*.end' => ['required', 'numeric'],
            'passages.*.moments' => ['nullable', 'array', 'max:20'],
        ]);

        if (! $catalogue->markReady((float) $data['duration'], $data['passages'], $data['voice_start'] ?? null)) {
            return response()->json(['message' => 'Les deux fichiers ne sont pas encore déposés.'], 409);
        }

        return response()->json(['ready' => true]);
    }
}
