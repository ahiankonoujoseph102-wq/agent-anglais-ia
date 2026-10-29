<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Live\LiveProviders;
use App\Support\Live\LiveVoiceSample;
use App\Support\Live\OpenAiRealtime;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * « Écouter en séance » (« Essai des voix ») : la phrase d'essai dite par une vraie connexion de séance avec cette
 * voix (LiveVoiceSample), sans micro ; l'extrait est gardé pour les écoutes suivantes.
 */
class VoiceLiveController extends Controller
{
    /** Extrait gardé, sinon de quoi se connecter (jeton Gemini ou adresse d'appel OpenAI). */
    public function start(Request $request, string $voice): JsonResponse
    {
        [$teacher, $provider] = $this->context($request, $voice);

        if (LiveVoiceSample::stored($provider, $voice, $teacher) !== null) {
            return response()->json(['stored' => $this->fileUrl($voice, $teacher, $provider)]);
        }

        $key = 'voice-live-trial:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('services.gemini.voice_trial_per_hour'))) {
            return response()->json(['message' => 'Trop d\'essais pour cette heure. Réessayez dans '.RateLimiter::availableIn($key).' secondes : les voix déjà écoutées restent disponibles.'], 429);
        }

        RateLimiter::hit($key, 3600);

        try {
            $connection = LiveVoiceSample::connection($provider, $voice, $teacher, $request->user()->id);
        } catch (GeminiUnavailable $e) {
            return response()->json(['message' => 'La séance d\'essai n\'a pas pu être ouverte. Cause : '.$e->getMessage()], 503);
        }

        LiveVoiceSample::start($request->user()->id, $provider, $voice, $teacher);

        return response()->json($connection + [
            'seconds' => LiveVoiceSample::SECONDS,
            'store_url' => $this->fileUrl($voice, $teacher, $provider),
        ]);
    }

    /** Voix reçue par le navigateur pendant la séance d'essai : gardée, puis l'appel est raccroché. */
    public function store(Request $request, string $voice, OpenAiRealtime $openai): JsonResponse
    {
        [$teacher, $provider] = $this->context($request, $voice);
        $request->validate(['audio' => ['required', 'file', 'max:4096']]);

        abort_unless(LiveVoiceSample::matches($request->user()->id, $provider, $voice, $teacher), 409);

        $file = $request->file('audio');
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 12);
        $extension = match (true) {
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE' => 'wav',
            str_starts_with($head, "\x1A\x45\xDF\xA3") => 'webm',
            default => null,
        };

        abort_if($extension === null, 422, 'Format audio inattendu.');

        PrivateStorage::disk()->put(LiveVoiceSample::basePath($provider, $voice, $teacher).'.'.$extension, (string) file_get_contents($file->getRealPath()));
        LiveVoiceSample::forget($request->user()->id);
        $this->hangUp($request, $openai);
        // Débit de cette voix en séance (Gemini : WAV), affiché dans « Essai des voix ».
        $pace = LiveVoiceSample::measure($provider, $voice, $teacher);

        return response()->json(['stored' => $this->fileUrl($voice, $teacher, $provider), 'pace' => $pace]);
    }

    public function show(Request $request, string $voice): Response
    {
        [$teacher, $provider] = $this->context($request, $voice);
        $stored = LiveVoiceSample::stored($provider, $voice, $teacher);

        abort_if($stored === null, 404);

        // Extraits gardés avant la mesure du débit : mesurés à la première écoute.
        if (LiveVoiceSample::pace($provider, $voice, $teacher) === null) {
            LiveVoiceSample::measure($provider, $voice, $teacher);
        }

        return response((string) PrivateStorage::disk()->get($stored['path']), 200, [
            'Content-Type' => $stored['type'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Fin de l'essai sans extrait (échec, page quittée) : l'appel OpenAI est raccroché tout de suite. */
    public function finish(Request $request, OpenAiRealtime $openai): JsonResponse
    {
        LiveVoiceSample::forget($request->user()->id);
        $this->hangUp($request, $openai);

        return response()->json(['finished' => true]);
    }

    private function hangUp(Request $request, OpenAiRealtime $openai): void
    {
        $call = Cache::pull('live-trial-call:'.$request->user()->id);

        if (is_array($call) && filled($call['call_id'] ?? null)) {
            $openai->hangup((string) $call['call_id']);
        }
    }

    /**
     * Professeur et fournisseur de la demande ; voix proposée pour eux, sinon 404.
     *
     * @return array{0: Teacher, 1: string}
     */
    private function context(Request $request, string $voice): array
    {
        $teacher = Teachers::find(is_string($request->query('professeur')) ? $request->query('professeur') : null) ?? Teachers::default();
        $provider = $request->query('fournisseur') === LiveProviders::OPENAI ? LiveProviders::OPENAI : LiveProviders::GEMINI;

        abort_unless(array_key_exists($voice, $provider === LiveProviders::OPENAI ? $teacher->openaiTrials() : $teacher->geminiTrials()), 404);

        return [$teacher, $provider];
    }

    private function fileUrl(string $voice, Teacher $teacher, string $provider): string
    {
        return route('admin.voices.live.file', ['voice' => $voice, 'professeur' => $teacher->key, 'fournisseur' => $provider]);
    }
}
