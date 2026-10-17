<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * « Essai des voix » : écouter les voix masculines proposées et choisir celle du professeur.
 */
class VoiceController extends Controller
{
    public function index(): View
    {
        return view('admin.voices.index', [
            'trials' => TeacherVoice::trials(),
            'current' => TeacherVoice::current(),
            'chosen' => TeacherVoice::isChosen(),
            'default' => config('services.gemini.voice'),
            'script' => VoiceSampler::script()['text'],
        ]);
    }

    /**
     * Extrait WAV d'une voix : gardé après la première écoute, limité par heure sinon.
     */
    public function sample(Request $request, string $voice, VoiceSampler $sampler): Response|JsonResponse
    {
        abort_unless(array_key_exists($voice, TeacherVoice::trials()), 404);

        $wav = $sampler->cached($voice);

        if ($wav === null) {
            $key = 'voice-trial:'.$request->user()->id;

            if (RateLimiter::tooManyAttempts($key, (int) config('services.gemini.voice_trial_per_hour'))) {
                return response()->json(['message' => 'Trop d\'essais pour cette heure. Réessayez dans '.RateLimiter::availableIn($key).' secondes : les voix déjà écoutées restent disponibles.'], 429);
            }

            RateLimiter::hit($key, 3600);

            try {
                $wav = $sampler->generate($voice);
            } catch (GeminiUnavailable $e) {
                report($e);

                return response()->json(['message' => $e->quotaExceeded
                    ? 'La limite de Gemini est atteinte pour le moment. Réessayez plus tard.'
                    : 'L\'extrait n\'a pas pu être créé. Réessayez dans quelques instants.'], 503);
            }
        }

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        $voice = (string) $request->input('voice');

        abort_unless(array_key_exists($voice, TeacherVoice::trials()), 422);

        TeacherVoice::choose($voice);

        return to_route('admin.voices.index')->with('status', "La voix « {$voice} » est enregistrée : elle sera utilisée pour toutes les prochaines conversations.");
    }
}
