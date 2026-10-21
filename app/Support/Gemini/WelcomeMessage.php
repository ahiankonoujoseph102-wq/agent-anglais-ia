<?php

namespace App\Support\Gemini;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Duration;
use App\Support\Greeting;
use App\Support\Plan;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechFiles;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\VoiceMessage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Message de bienvenue de Teacher Joe (config/teacher.php → welcome), à la première ouverture d'un accès :
 * félicitations pour la formule, questionnaire, règles de la séance orale, « Mes cours ».
 *
 * Chaque morceau (l'ouverture, puis chaque paragraphe) est une synthèse vocale à part, gardée sur le disque
 * privé sous le nom du fournisseur et de la voix qui l'ont produit :
 * - les paragraphes sont communs à tous les apprenants d'une même formule : préparés à l'avance ;
 * - l'ouverture (salutation et prénom) est la seule partie propre à chaque prénom, gardée elle aussi
 *   (deux Koffi qui reçoivent leur accès le soir partagent le même fichier).
 * UNE seule voix par message : le fournisseur du message (SpeechProviders::choose) dit aussi la salutation ;
 * si elle échoue, elle reste en texte et les paragraphes de ce même fournisseur sont joués.
 * En cas d'échec, aucune nouvelle demande pendant quelques minutes.
 */
class WelcomeMessage
{
    public const DIRECTORY = 'welcome-messages';

    /** Sous-dossier des ouvertures (une par prénom), jamais effacées par purgeOutdated(). */
    public const OPENINGS = 'openings';

    /** Début du nom des anciennes ouvertures (avant le choix du fournisseur), effacées par purgeOutdated(). */
    public const LEGACY_OPENING_PREFIX = 'opening-';

    /** Pause après un échec, en secondes (aucune nouvelle demande à Google pendant ce temps). */
    public const RETRY_AFTER = 600;

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
        return array_values(self::namedParagraphs($planName, $dailyMinutes, $programReady));
    }

    /**
     * Paragraphes avec leur nom (clé de config/teacher.php → welcome.paragraphs).
     *
     * @return array<string, string>
     */
    private static function namedParagraphs(string $planName, int $dailyMinutes, bool $programReady): array
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
            ':course_minutes' => Duration::format((int) config('platform.courses.max_session_minutes') * 60),
        ];

        return array_map(fn (string $text) => strtr($text, $replace), $paragraphs);
    }

    /**
     * Les messages communs à préparer : un par formule (la formule recommandée d'abord), programme à
     * construire puis déjà prêt. Les paragraphes identiques d'un message à l'autre partagent leur fichier.
     *
     * @return list<VoiceMessage>
     */
    public static function messages(): array
    {
        $plans = Plan::all();
        uasort($plans, fn (Plan $a, Plan $b) => (int) $b->recommended <=> (int) $a->recommended);
        $messages = [];

        foreach ([false, true] as $programReady) {
            foreach ($plans as $plan) {
                $pieces = [];
                $position = 0;

                foreach (self::namedParagraphs($plan->name, $plan->dailyMinutes, $programReady) as $name => $text) {
                    $position++;
                    $name = $programReady && $name === 'questionnaire' ? 'program_ready' : $name;
                    $pieces[] = ['label' => "Bienvenue, paragraphe {$position} ({$name}, {$plan->name})", 'text' => $text];
                }

                $messages[] = self::voiceMessage(
                    'welcome:'.$plan->key.($programReady ? ':program' : ''),
                    'Bienvenue, formule '.$plan->name.($programReady ? ', programme déjà prêt' : ''),
                    $pieces,
                );
            }
        }

        return $messages;
    }

    /**
     * Le message de cet apprenant (ses paragraphes, sans l'ouverture).
     */
    public static function message(array $texts): VoiceMessage
    {
        $pieces = [];

        foreach ($texts['paragraphs'] as $position => $text) {
            $pieces[] = ['label' => 'Bienvenue, paragraphe '.($position + 1).' ('.$texts['plan'].')', 'text' => $text];
        }

        return self::voiceMessage('welcome', 'Bienvenue, formule '.$texts['plan'], $pieces);
    }

    /**
     * Le fournisseur qui dit tout le message de cet apprenant, salutation comprise.
     */
    public static function providerFor(array $texts): ?SpeechProvider
    {
        return self::message($texts)->provider();
    }

    /**
     * @param  list<array{label: string, text: string}>  $pieces
     */
    private static function voiceMessage(string $key, string $label, array $pieces): VoiceMessage
    {
        return new VoiceMessage($key, $label, $pieces, (string) config('teacher.welcome.style'), fn (string $text, SpeechProvider $provider) => self::path($text, $provider));
    }

    /**
     * Paragraphes communs, sans doublon, dans l'ordre des messages. Le nom sert aux journaux.
     *
     * @return list<array{label: string, text: string}>
     */
    public static function commonParagraphs(): array
    {
        $pieces = [];

        foreach (self::messages() as $message) {
            foreach ($message->pieces as $piece) {
                $pieces[$piece['text']] ??= $piece;
            }
        }

        return array_values($pieces);
    }

    /**
     * Chemins des paragraphes valables aujourd'hui, chez chaque fournisseur connu (sa voix et son modèle
     * actuels, textes actuels, chaque formule, programme à construire ou déjà prêt).
     *
     * @return list<string>
     */
    public static function currentParagraphPaths(): array
    {
        $paths = [];

        foreach (SpeechProviders::all() as $provider) {
            foreach (self::commonParagraphs() as $piece) {
                $paths[] = self::path($piece['text'], $provider);
            }
        }

        return $paths;
    }

    /**
     * Le paragraphe commun est-il déjà prêt chez ce fournisseur ?
     */
    public static function isReady(string $text, SpeechProvider $provider): bool
    {
        try {
            return PrivateStorage::disk()->exists(self::path($text, $provider));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Renomme les paragraphes de Gemini gardés avant le choix du fournisseur (« {voix}-{empreinte}.wav ») :
     * ce qui a déjà été préparé n'est pas redemandé.
     */
    public static function adoptLegacyFiles(): int
    {
        $disk = PrivateStorage::disk();
        $gemini = SpeechProviders::find('gemini');
        $files = SpeechFiles::index([self::DIRECTORY]);
        $adopted = 0;

        foreach (self::commonParagraphs() as $piece) {
            $new = self::path($piece['text'], $gemini);
            $old = self::DIRECTORY.'/'.substr(basename($new), strlen($gemini->name()) + 1);

            if (isset($files[$old]) && ! isset($files[$new])) {
                $disk->move($old, $new);
                $files[$new] = true;
                $adopted++;
            }
        }

        return $adopted;
    }

    /**
     * Efface les paragraphes gardés qui ne correspondent plus à aucun texte actuel (phrase corrigée,
     * ancien message, autre voix, autre modèle) : aucune vieille version n'est gardée. Les ouvertures
     * (une par prénom, dans leur sous-dossier) ne sont pas concernées.
     */
    public static function purgeOutdated(): int
    {
        $disk = PrivateStorage::disk();
        $current = array_flip(self::currentParagraphPaths());
        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (! isset($current[$file])) {
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
     * Chemin du fichier : porte le fournisseur et la voix ; change si le fournisseur, la voix, le modèle,
     * le texte ou le ton changent.
     */
    public static function path(string $text, SpeechProvider $provider, bool $opening = false): string
    {
        $hash = substr(sha1(implode('|', array_filter([
            $provider->model(), $provider->voice(), $text, config('teacher.welcome.style'), $provider->settings(),
        ], fn ($part) => $part !== ''))), 0, 20);

        return self::DIRECTORY.'/'.($opening ? self::OPENINGS.'/' : '').$provider->name().'-'.$provider->voice().'-'.$hash.'.wav';
    }

    /**
     * Le fichier WAV d'un morceau chez le fournisseur du message, ou null s'il n'est pas disponible. Pendant
     * l'écoute, seule l'ouverture (salutation et prénom, courte) est générée, par ce même fournisseur ; les
     * paragraphes communs sont préparés à l'avance (commande app:prepare-voice, bouton de « Diagnostic de la
     * voix ») : un paragraphe pas encore prêt reste affiché en texte, sans aucune demande.
     */
    public function get(string $text, SpeechProvider $provider, bool $opening = false): ?string
    {
        $path = self::path($text, $provider, $opening);
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        if (! $opening || ! $provider->hasKey()) {
            return null;
        }

        $failed = 'welcome-message-failed:'.$path;

        if (Cache::has($failed)) {
            return null;
        }

        // Un seul appel à la fois pour une même salutation (deux Koffi qui arrivent ensemble).
        $wait = (int) config('services.gemini.tts_listen_timeout') + 5;

        try {
            return Cache::lock('welcome-message:'.$path, $wait + 5)->block($wait, function () use ($text, $provider, $path, $failed, $disk) {
                if ($disk->exists($path)) {
                    return $disk->get($path);
                }

                try {
                    $wav = $provider->synthesize($text, (string) config('teacher.welcome.style'), 'Bienvenue, salutation', (int) config('services.gemini.tts_listen_timeout'));
                    $wav = VoiceSampler::store($path, $wav, 'Bienvenue, salutation', $provider);
                } catch (GeminiUnavailable) {
                    // Cause déjà journalisée ; aucune nouvelle demande pendant quelques minutes.
                    Cache::put($failed, true, self::RETRY_AFTER);

                    return null;
                }

                return $wav;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }
}
