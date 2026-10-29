<?php

namespace App\Support\Gemini;

use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Support\Live\LiveLog;
use App\Support\Live\LivePage;
use App\Support\Live\LiveProviders;
use App\Support\Live\LiveUnavailable;
use App\Support\Live\OpenAiRealtime;
use App\Support\Live\Transcripts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Choisit le fournisseur d'une séance en direct, délivre de quoi s'y connecter et prépare la réponse envoyée
 * au navigateur.
 *
 * Le fournisseur est choisi au DÉBUT de la séance (LIVE_PROVIDERS, Gemini puis OpenAI) : si le jeton Gemini
 * ne peut pas être créé (erreur, limite, délai), ou si Gemini a été mis de côté après une limite, la séance
 * commence chez OpenAI. Ensuite, chaque reconnexion ou renouvellement se fait chez LE MÊME fournisseur. Seule
 * exception : le navigateur n'a pas pu se connecter avant que la conversation commence (aucune parole
 * échangée, dans les deux premières minutes) ; il redemande alors en nommant le fournisseur en échec.
 */
class LiveConnection
{
    public function __construct(private LiveClient $client) {}

    /**
     * Validité RÉELLE du jeton Gemini : toute la séance (la fin autorisée, plus ce que la séance peut encore gagner,
     * l'évaluation avant son verdict : la mini-leçon et les questions), plus une petite marge. Aucun renouvellement
     * prévu en pleine conversation (incident du 28 octobre 2026 : jeton de 5 minutes expiré pendant une réponse,
     * « auth token has expired ») ; seule la fermeture annoncée par Google (goAway, environ 10 minutes) en demande un.
     */
    public static function tokenExpiry(Carbon $deadline, int $mayExtendBy = 0): Carbon
    {
        return $deadline->copy()->addSeconds(max(0, $mayExtendBy) + (int) config('services.gemini.token_margin_seconds'));
    }

    /**
     * Expiration notée sur la séance pour le décompte d'un navigateur disparu (closeIfOver) : courte, comme avant
     * (token_minutes, jamais au-delà de la fin autorisée), pour qu'un élève dont le téléphone s'éteint ne perde pas
     * toute sa séance.
     */
    public static function accountingExpiry(Carbon $deadline): Carbon
    {
        return now()->addMinutes((int) config('services.gemini.token_minutes'))->min($deadline);
    }

    /**
     * Délivre un jeton (Gemini) ou prépare l'appel (OpenAI), avant toute écriture en base.
     *
     * @param  array<string, mixed>  $setup
     * @param  string|null  $failed  fournisseur auquel le navigateur n'a pas pu se connecter (aucune parole échangée)
     * @param  int  $mayExtendBy  secondes que la séance peut encore gagner (évaluation avant son verdict)
     * @return array{provider: string, expires_at: Carbon, valid_until?: Carbon, token?: string, websocket_url?: string}
     *
     * @throws LiveUnavailable
     */
    public function request(array $setup, Carbon $deadline, Assessment|CourseSession|Lesson|LevelTest|null $conversation = null, ?string $failed = null, int $mayExtendBy = 0): array
    {
        $current = $conversation?->liveProvider();

        if ($conversation !== null && $conversation->isInProgress()) {
            // Reconnexion : la coupure éventuelle est comptée (le temps était en pause), la fin est repoussée d'autant.
            $conversation->markReconnected();
            $conversation->save();
            $deadline = $conversation->deadline();
        }

        if ($failed !== null && in_array($failed, LiveProviders::all(), true)) {
            LiveLog::failure($failed, 'Connexion du navigateur', 'Connexion impossible avant la première parole.');
        }

        if ($current === null) {
            $providers = LiveProviders::ordered($failed !== null ? [$failed] : []) ?: LiveProviders::ordered();
        } elseif ($failed === $current && $conversation->canSwitchProvider()) {
            // Rien n'a été dit : la séance peut encore commencer chez le fournisseur suivant (s'il y en a un).
            $providers = LiveProviders::ordered([$current]) ?: [$current];
        } else {
            // En cours de séance : le même fournisseur. Le suivant seulement s'il REFUSE la reconnexion (jamais pour
            // une simple lenteur ni un réseau d'apprenant) ; le navigateur lui redonne les derniers échanges.
            $providers = [$current, ...LiveProviders::ordered([$current])];
        }

        $last = null;

        foreach ($providers as $provider) {
            try {
                if ($current !== null && $provider !== $current) {
                    // La reprise d'une conversation Gemini ne vaut que chez le fournisseur qui l'a donnée.
                    $setup['sessionResumption'] = (object) [];
                }

                return $this->open($provider, $setup, $deadline, $mayExtendBy);
            } catch (GeminiUnavailable $e) {
                LiveLog::failure($provider, 'Création du jeton', $e->getMessage(), $e->httpStatus, $e->quotaExceeded);

                if ($e->pausesProvider()) {
                    LiveProviders::pause($provider);
                }

                $last = $e;
            }
        }

        throw new LiveUnavailable(
            $last?->getMessage() ?? 'Aucun fournisseur de séance en direct n\'a de clé.',
            quotaExceeded: $last?->quotaExceeded ?? false,
            httpStatus: $last?->httpStatus,
        );
    }

    /**
     * @param  array<string, mixed>  $setup
     * @return array{provider: string, expires_at: Carbon, valid_until?: Carbon, token?: string, websocket_url?: string}
     *
     * @throws GeminiUnavailable
     */
    private function open(string $provider, array $setup, Carbon $deadline, int $mayExtendBy = 0): array
    {
        if (! LiveProviders::hasKey($provider)) {
            throw new LiveUnavailable('Clé absente.', provider: $provider);
        }

        if ($provider === LiveProviders::OPENAI) {
            // L'appel est ouvert par le serveur dès que le navigateur envoie sa description WebRTC (route live.call),
            // dans la minute : aucun jeton ne quitte le serveur.
            return [
                'provider' => LiveProviders::OPENAI,
                'expires_at' => now()->addSeconds((int) config('services.gemini.connect_window_seconds'))->min($deadline),
            ];
        }

        $validUntil = self::tokenExpiry($deadline, $mayExtendBy);

        // Gemini, clé principale ou deuxième clé : même jeton, même navigateur ; seule la clé change.
        return ['provider' => $provider] + $this->client->createToken($setup, $validUntil, LiveProviders::geminiKey($provider))
            + ['expires_at' => self::accountingExpiry($deadline), 'valid_until' => $validUntil];
    }

    /**
     * Niveau qui règle le débit de la voix (comme les consignes, TeacherSetup::paceLevel) : évaluation gratuite :
     * niveau inconnu ; sinon le niveau de l'élève.
     */
    public static function paceLevel(Assessment|CourseSession|Lesson|LevelTest $conversation): string
    {
        return TeacherSetup::paceLevel($conversation instanceof Assessment ? 'assessment' : 'conversation', $conversation->loadMissing('user')->user);
    }

    /**
     * Note le fournisseur et le jeton délivré sur la conversation et construit la réponse JSON.
     *
     * @param  array{provider: string, expires_at: Carbon, valid_until?: Carbon, token?: string, websocket_url?: string}  $token
     * @param  array<string, mixed>  $setup
     * @param  array<string, string>  $cues
     * @return array<string, mixed>
     */
    public function attach(Assessment|CourseSession|Lesson|LevelTest $conversation, array $token, array $setup, array $cues, int $warningSeconds, bool $resumed): array
    {
        $provider = $token['provider'];
        $conversation->live_provider = $provider;
        $conversation->token_expires_at = $token['expires_at'];
        $conversation->connections++;
        $conversation->last_seen_at = now();
        $conversation->save();
        // Cette page devient celle de la séance : une autre page encore ouverte s'arrêtera d'elle-même.
        LivePage::claim($conversation);

        $connection = $provider === LiveProviders::OPENAI
            ? [
                'call_url' => self::callUrl($conversation, OpenAiRealtime::session($setup, self::paceLevel($conversation), $conversation->teacher())),
                // Un appel OpenAI dure jusqu'à la fin de la séance (le serveur raccroche) : rien à renouveler.
                'token_expires_in' => max(0, $conversation->remainingSeconds()) + 3600,
            ]
            : [
                'websocket_url' => $token['websocket_url'],
                'setup' => $setup,
                'token_expires_in' => max(0, (int) now()->diffInSeconds($token['valid_until'] ?? $token['expires_at'], false)),
            ];

        return ['provider' => $provider] + $connection + [
            // Texte de la conversation (jamais l'audio), envoyé par petits lots : Transcripts.
            'transcript_url' => route('live.transcript', ['type' => Transcripts::typeOf($conversation), 'id' => $conversation->getKey()]),
            'remaining_seconds' => $conversation->remainingSeconds(),
            'warning_seconds' => $warningSeconds,
            'heartbeat_seconds' => (int) config('platform.live.heartbeat_seconds'),
            'cues' => $cues + ['pause_resume' => TeacherSetup::cue('pause_resume'), 'nudge' => TeacherSetup::cue('nudge')],
            // Micro coupé pendant la voix du professeur, et encore ce délai après (anti-écho).
            'echo_guard_ms' => (int) config('platform.live.echo_guard_ms'),
            // Au-delà de cette durée de pause, la conversation se termine (le temps continue en pause).
            'pause_max_seconds' => (int) config('platform.live.pause_max_seconds'),
            'resumed' => $resumed,
        ];
    }

    /**
     * Adresse à usage unique où le navigateur envoie sa description WebRTC : la configuration de l'appel
     * (consignes comprises) reste sur le serveur, une minute au plus.
     *
     * @param  array<string, mixed>  $session
     */
    public static function callUrl(Assessment|CourseSession|Lesson|LevelTest|null $conversation, array $session, ?int $userId = null, int $trialSeconds = 0): string
    {
        $ticket = Str::random(40);

        Cache::put('live-call:'.$ticket, [
            'class' => $conversation !== null ? $conversation::class : null,
            'id' => $conversation?->getKey(),
            'user_id' => $userId ?? $conversation->user_id,
            'session' => $session,
            'trial_seconds' => $trialSeconds,
        ], now()->addSeconds((int) config('services.gemini.connect_window_seconds') + 30));

        return route('live.call', $ticket);
    }
}
