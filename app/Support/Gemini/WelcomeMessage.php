<?php

namespace App\Support\Gemini;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Duration;
use App\Support\Greeting;
use App\Support\Plan;
use App\Support\PrivateStorage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Message de bienvenue de Teacher Joe (config/teacher.php → welcome), à la première ouverture d'un accès :
 * félicitations pour la formule, questionnaire, règles de la séance orale, « Mes cours ».
 *
 * Chaque morceau (l'ouverture, puis chaque paragraphe) est une synthèse vocale à part, avec la voix
 * de Teacher Joe, gardée sur le disque privé :
 * - les paragraphes sont communs à tous les apprenants d'une même formule : générés UNE seule fois ;
 * - l'ouverture (salutation et prénom) est la seule partie propre à chaque prénom, gardée elle aussi
 *   (deux Koffi qui reçoivent leur accès le soir partagent le même fichier).
 * Le navigateur lit les morceaux l'un après l'autre : le premier démarre vite, même quand rien n'est
 * encore généré. En cas d'échec, aucune nouvelle demande à Google pendant quelques minutes.
 */
class WelcomeMessage
{
    public const DIRECTORY = 'welcome-messages';

    /** Début du nom des fichiers d'ouverture (un par prénom), jamais effacés par purgeOutdated(). */
    public const OPENING_PREFIX = 'opening-';

    /** Pause après un échec, en secondes (aucune nouvelle demande à Google pendant ce temps). */
    public const RETRY_AFTER = 600;

    public function __construct(private VoiceSampler $sampler) {}

    /**
     * L'accès dont parle le message : l'accès en cours, sinon le dernier (réécoute après la fin d'un accès).
     */
    public static function subscription(User $user): ?Subscription
    {
        return $user->activeSubscription()->first() ?? $user->subscriptions()->latest('starts_at')->latest('id')->first();
    }

    /**
     * Les textes du message pour cet apprenant : ouverture et paragraphes ; null sans aucun accès.
     * Le nom de la formule et ses minutes viennent de son accès ; les limites, de la configuration.
     *
     * @return array{period: string, first_name: string, opening: string, paragraphs: list<string>, plan: string}|null
     */
    public static function texts(User $user, ?string $period = null): ?array
    {
        $subscription = self::subscription($user);

        if ($subscription === null) {
            return null;
        }

        $period ??= Greeting::period($user);
        $words = Greeting::words($period);
        $firstName = Greeting::firstName($user->name);
        $planName = Plan::find($subscription->plan)?->name ?? $subscription->plan_name;

        $opening = strtr((string) config('teacher.welcome.opening'), [
            ':greeting' => $words['fr'], ':english' => $words['en'], ':name' => $firstName,
        ]);

        return [
            'period' => $period,
            'first_name' => $firstName,
            'opening' => $opening,
            'paragraphs' => self::paragraphs($planName, (int) $subscription->daily_minutes, $user->activeProgram()->exists()),
            'plan' => $planName,
        ];
    }

    /**
     * Paragraphes du message, marqueurs remplis ; « program_ready » remplace le questionnaire quand
     * le programme existe déjà.
     *
     * @return list<string>
     */
    private static function paragraphs(string $planName, int $dailyMinutes, bool $programReady): array
    {
        $paragraphs = (array) config('teacher.welcome.paragraphs', []);

        if ($programReady && isset($paragraphs['questionnaire'])) {
            $paragraphs['questionnaire'] = (string) config('teacher.welcome.program_ready');
        }

        $replace = [
            ':platform' => (string) config('platform.name'),
            ':teacher' => (string) config('avatar.model.name'),
            ':plan' => $planName,
            ':daily_duration' => Duration::format($dailyMinutes * 60),
            ':course_minutes' => (string) config('platform.courses.session_minutes'),
            ':course_sessions' => (string) config('platform.courses.sessions_per_day'),
        ];

        return array_values(array_map(fn (string $text) => strtr($text, $replace), $paragraphs));
    }

    /**
     * Chemins des paragraphes valables aujourd'hui (voix actuelle, textes actuels, chaque formule,
     * programme à construire ou déjà prêt).
     *
     * @return list<string>
     */
    public static function currentParagraphPaths(): array
    {
        $voice = (string) TeacherVoice::current();
        $texts = [];

        foreach (Plan::all() as $plan) {
            array_push($texts, ...self::paragraphs($plan->name, $plan->dailyMinutes, false), ...self::paragraphs($plan->name, $plan->dailyMinutes, true));
        }

        return array_values(array_unique(array_map(fn (string $text) => self::path($text, $voice), $texts)));
    }

    /**
     * Efface les paragraphes gardés qui ne correspondent plus à aucun texte actuel (phrase corrigée,
     * ancien message, autre voix, autre modèle) : aucune vieille version n'est gardée. Les ouvertures
     * (une par prénom) ne sont pas concernées.
     */
    public static function purgeOutdated(): int
    {
        $disk = PrivateStorage::disk();
        $current = array_flip(self::currentParagraphPaths());
        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (! str_starts_with(basename($file), self::OPENING_PREFIX) && ! isset($current[$file])) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
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
    public static function path(string $text, string $voice, bool $opening = false): string
    {
        $hash = substr(sha1(implode('|', [
            config('services.gemini.tts_model'), $voice, $text, config('teacher.welcome.style'),
        ])), 0, 20);

        return self::DIRECTORY.'/'.($opening ? self::OPENING_PREFIX : '').$voice.'-'.$hash.'.wav';
    }

    /**
     * Le fichier WAV du morceau, généré au besoin ; null s'il n'est pas disponible.
     */
    public function get(string $text, bool $opening = false): ?string
    {
        $voice = (string) TeacherVoice::current();
        $path = self::path($text, $voice, $opening);
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
            return Cache::lock('welcome-message:'.$path, 40)->block(35, fn () => $this->generate($text, $voice, $path, $failed, $opening));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function generate(string $text, string $voice, string $path, string $failed, bool $opening): ?string
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

        // Un nouveau paragraphe remplace souvent une ancienne version : on la supprime.
        if (! $opening) {
            self::purgeOutdated();
        }

        return $wav;
    }
}
