<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveClient;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\LiveLog;
use App\Support\Live\LiveProviders;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Séances en direct dans le « Diagnostic des fournisseurs » (administrateurs) : « Tester » vérifie la création
 * d'un jeton chez un fournisseur ; « Essayer une conversation de 30 secondes » fait entendre Teacher Joe chez
 * CE fournisseur (sans secours), sur le même écran de conversation que les apprenants. Rien n'est enregistré
 * comme séance ; la clé et le jeton ne sont jamais affichés.
 */
class LiveTrialController extends Controller
{
    public const SECONDS = 30;

    /**
     * « Tester » : un jeton de la durée la plus courte possible, jamais gardé ni affiché.
     */
    public function test(string $provider, LiveClient $gemini, OpenAiRealtime $openai): RedirectResponse
    {
        $label = LiveProviders::label($provider);
        $started = hrtime(true);

        try {
            if (! LiveProviders::hasKey($provider)) {
                throw new GeminiUnavailable('clé absente.');
            }

            if ($provider === LiveProviders::OPENAI) {
                $openai->testToken();
            } else {
                $gemini->createToken(['model' => 'models/'.config('services.gemini.live_model'), 'generationConfig' => ['responseModalities' => ['AUDIO']]], now()->addMinute());
            }
        } catch (GeminiUnavailable $e) {
            LiveLog::failure($provider, 'Diagnostic : jeton', $e->getMessage(), $e->httpStatus, $e->quotaExceeded);

            return redirect()->to(route('admin.text-diagnostic.index').'#direct')->with('diagnostic', [
                'ok' => false,
                'text' => $label.' (séances en direct) : échec : '.$e->getMessage(),
            ]);
        }

        $seconds = round((hrtime(true) - $started) / 1e9, 1);

        return redirect()->to(route('admin.text-diagnostic.index').'#direct')->with('diagnostic', [
            'ok' => true,
            'text' => $label.' (séances en direct) : réussi : jeton créé en '.number_format($seconds, 1, ',', ' ').($seconds >= 2 ? ' secondes' : ' seconde').' avec le modèle « '.LiveProviders::model($provider).' ».',
        ]);
    }

    public function show(string $provider): View
    {
        $label = LiveProviders::label($provider);

        return view('live.session', [
            'mode' => 'trial',
            'title' => 'Essai de conversation ('.$label.')',
            'intro' => 'Une conversation de 30 secondes avec '.config('avatar.model.name').' chez '.$label.', sans secours : vous entendez sa voix et son ton. Rien n\'est compté comme une séance.',
            'remainingSeconds' => self::SECONDS,
            'urls' => [
                'connect' => route('admin.live-trial.connect', $provider),
                'signal' => route('admin.live-trial.signal', $provider),
                'finish' => route('admin.live-trial.finish', $provider),
                'result' => route('admin.live-trial.finish', $provider),
                'done' => route('admin.text-diagnostic.index').'#direct',
            ],
        ]);
    }

    public function connect(Request $request, string $provider, LiveClient $gemini): JsonResponse
    {
        $user = $request->user();
        $trial = Cache::get('live-trial:'.$user->id);

        // Nouvel essai si aucun n'est en cours (ou chez un autre fournisseur).
        if (! is_array($trial) || $trial['provider'] !== $provider || self::remaining($trial) <= 0) {
            $trial = ['provider' => $provider, 'started_at' => now()->getTimestamp()];
            Cache::put('live-trial:'.$user->id, $trial, now()->addMinutes(10));
        }

        $remaining = self::remaining($trial);
        $setup = TeacherSetup::trial($user);

        try {
            if (! LiveProviders::hasKey($provider)) {
                throw new GeminiUnavailable('Clé absente.');
            }

            $connection = $provider === LiveProviders::OPENAI
                ? [
                    'call_url' => LiveConnection::callUrl(null, OpenAiRealtime::session($setup), $user->id, $remaining + 5),
                    'token_expires_in' => $remaining + 3600,
                ]
                : [
                    'websocket_url' => $gemini->createToken($setup, now()->addSeconds($remaining))['websocket_url'],
                    'setup' => $setup,
                    'token_expires_in' => $remaining,
                ];
        } catch (GeminiUnavailable $e) {
            LiveLog::failure($provider, 'Diagnostic : essai de conversation', $e->getMessage(), $e->httpStatus, $e->quotaExceeded);

            return response()->json(['message' => LiveProviders::label($provider).' : '.$e->getMessage(), 'quota' => $e->quotaExceeded], 503);
        }

        return response()->json(['provider' => $provider] + $connection + [
            'remaining_seconds' => $remaining,
            'warning_seconds' => 10,
            'heartbeat_seconds' => 5,
            'cues' => [
                'start' => TeacherSetup::cue('trial_start'),
                'resume' => TeacherSetup::cue('resume'),
                'warning' => TeacherSetup::cue('trial_warning'),
                'pause_resume' => TeacherSetup::cue('pause_resume'),
            ],
            'echo_guard_ms' => (int) config('platform.live.echo_guard_ms'),
            'pause_max_seconds' => self::SECONDS,
            'resumed' => false,
        ]);
    }

    /**
     * Signal : à la fin des 30 secondes, l'appel OpenAI est raccroché par le serveur.
     */
    public function signal(Request $request, string $provider, OpenAiRealtime $openai): JsonResponse
    {
        $trial = Cache::get('live-trial:'.$request->user()->id);
        $remaining = is_array($trial) ? self::remaining($trial) : 0;

        if ($remaining <= 0) {
            $this->hangUp($request, $openai);
        }

        return response()->json(['active' => $remaining > 0, 'remaining_seconds' => $remaining]);
    }

    public function finish(Request $request, string $provider, OpenAiRealtime $openai): JsonResponse
    {
        $this->hangUp($request, $openai);
        Cache::forget('live-trial:'.$request->user()->id);

        return response()->json(['status' => 'Terminé']);
    }

    private function hangUp(Request $request, OpenAiRealtime $openai): void
    {
        $call = Cache::pull('live-trial-call:'.$request->user()->id);

        if (is_array($call) && filled($call['call_id'] ?? null)) {
            $openai->hangup((string) $call['call_id']);
        }
    }

    /**
     * @param  array{provider: string, started_at: int}  $trial
     */
    private static function remaining(array $trial): int
    {
        return max(0, self::SECONDS - (now()->getTimestamp() - (int) $trial['started_at']));
    }
}
