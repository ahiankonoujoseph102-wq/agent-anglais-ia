<?php

namespace App\Support\Gemini;

use App\Models\User;
use App\Support\Greeting;
use App\Support\Plan;
use App\Support\PrivateStorage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Messages de bienvenue de Teacher Joe (config/teacher.php → welcome) : après l'inscription (« signup »)
 * et à la première ouverture d'un accès (« plan »).
 *
 * Chaque morceau (l'ouverture, puis chaque paragraphe) est une synthèse vocale à part, avec la voix
 * de Teacher Joe, gardée sur le disque privé :
 * - les paragraphes sont communs à tous les apprenants : générés UNE seule fois ;
 * - l'ouverture (salutation et prénom) est la seule partie propre à chaque prénom, gardée elle aussi
 *   (deux Koffi qui s'inscrivent le soir partagent le même fichier).
 * Le navigateur lit les morceaux l'un après l'autre : le premier démarre vite, même quand rien n'est
 * encore généré. En cas d'échec, aucune nouvelle demande à Google pendant quelques minutes.
 */
class WelcomeMessage
{
    public const SIGNUP = 'signup';

    public const PLAN = 'plan';

    public const DIRECTORY = 'welcome-messages';

    /** Pause après un échec, en secondes (aucune nouvelle demande à Google pendant ce temps). */
    public const RETRY_AFTER = 600;

    public function __construct(private VoiceSampler $sampler) {}

    public static function isKind(string $kind): bool
    {
        return in_array($kind, [self::SIGNUP, self::PLAN], true);
    }

    /**
     * Les textes du message pour cet apprenant : ouverture et paragraphes. Les durées et limites viennent
     * de la configuration ; le nom de la formule, de la formule de l'apprenant.
     *
     * @return array{kind: string, period: string, first_name: string, opening: string, paragraphs: list<string>, plan: ?string}
     */
    public static function texts(User $user, string $kind, ?string $period = null): array
    {
        $period ??= Greeting::period($user);
        $words = Greeting::words($period);
        $firstName = Greeting::firstName($user->name);
        $planName = null;
        $key = self::SIGNUP;

        if ($kind === self::PLAN) {
            $subscription = $user->activeSubscription()->first();
            $planName = $subscription ? (Plan::find($subscription->plan)?->name ?? $subscription->plan_name) : null;
            $key = $user->activeProgram()->exists() ? 'plan_ready' : self::PLAN;
        }

        $replace = [
            ':platform' => (string) config('platform.name'),
            ':teacher' => (string) config('avatar.model.name'),
            ':assessment_minutes' => (string) config('platform.assessment.max_minutes'),
            ':course_minutes' => (string) config('platform.courses.session_minutes'),
            ':course_sessions' => (string) config('platform.courses.sessions_per_day'),
            ':plan' => (string) $planName,
        ];

        $opening = strtr((string) config('teacher.welcome.opening'), [
            ':greeting' => $words['fr'], ':english' => $words['en'], ':name' => $firstName,
        ]);

        return [
            'kind' => $kind,
            'period' => $period,
            'first_name' => $firstName,
            'opening' => $opening,
            'paragraphs' => array_values(array_map(fn (string $text) => strtr($text, $replace), (array) config("teacher.welcome.{$key}", []))),
            'plan' => $planName,
        ];
    }

    /**
     * Morceau n° $index (0 : l'ouverture ; 1 et suivants : les paragraphes), ou null s'il n'existe pas.
     */
    public static function piece(array $texts, int $index): ?string
    {
        if ($index === 0) {
            return $texts['opening'];
        }

        return $texts['paragraphs'][$index - 1] ?? null;
    }

    /**
     * Nombre de morceaux audio : l'ouverture et les paragraphes.
     */
    public static function count(array $texts): int
    {
        return 1 + count($texts['paragraphs']);
    }

    /**
     * Chemin du fichier : change si la voix, le modèle, le texte ou le ton changent.
     */
    public static function path(string $text, string $voice): string
    {
        $hash = substr(sha1(implode('|', [
            config('services.gemini.tts_model'), $voice, $text, config('teacher.welcome.style'),
        ])), 0, 20);

        return self::DIRECTORY.'/'.$voice.'-'.$hash.'.wav';
    }

    /**
     * Le fichier WAV du morceau, généré au besoin ; null s'il n'est pas disponible.
     */
    public function get(string $text): ?string
    {
        $voice = (string) TeacherVoice::current();
        $path = self::path($text, $voice);
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $failed = 'welcome-message-failed:'.$path;

        if (Cache::has($failed)) {
            return null;
        }

        // Un seul appel à la fois pour un même morceau (deux apprenants qui arrivent ensemble).
        try {
            return Cache::lock('welcome-message:'.$path, 40)->block(35, fn () => $this->generate($text, $voice, $path, $failed));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function generate(string $text, string $voice, string $path, string $failed): ?string
    {
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        try {
            $wav = $this->sampler->synthesize($voice, $text, (string) config('teacher.welcome.style'));
        } catch (GeminiUnavailable $e) {
            report($e);
            Cache::put($failed, true, self::RETRY_AFTER);

            return null;
        }

        $disk->put($path, $wav);

        return $wav;
    }
}
