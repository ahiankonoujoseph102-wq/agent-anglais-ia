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
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use App\Support\Videos\PresentationScripts;
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
 *
 * Un message par professeur (Teacher Joe, Teacher Rose) : son nom, sa voix, son ton et ses accords. Les fichiers
 * de Teacher Joe gardent exactement leur nom d'avant (rien n'est refait pour lui).
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
     * @return array{period: string, first_name: string, opening: string, paragraphs: list<string>, plan: string, teacher: Teacher, introduction: ?string}|null
     */
    public static function texts(User $user, ?string $period = null, ?Teacher $teacher = null): ?array
    {
        $teacher ??= Teachers::forUser($user);
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
            'paragraphs' => self::paragraphs($planName, (int) $subscription->daily_minutes, $user->activeProgram()->exists(), $teacher),
            'plan' => $planName,
            'teacher' => $teacher,
            // Paragraphe où le professeur se présente : extrait de sa voix sur la page de bienvenue.
            'introduction' => self::namedParagraphs($planName, (int) $subscription->daily_minutes, $user->activeProgram()->exists(), $teacher)['teacher'] ?? null,
        ];
    }

    /**
     * Paragraphes du message, marqueurs remplis ; « program_ready » remplace le questionnaire quand
     * le programme existe déjà.
     *
     * @return list<string>
     */
    private static function paragraphs(string $planName, int $dailyMinutes, bool $programReady, Teacher $teacher): array
    {
        return array_values(self::namedParagraphs($planName, $dailyMinutes, $programReady, $teacher));
    }

    /**
     * Paragraphes avec leur nom (clé de config/teacher.php → welcome.paragraphs).
     *
     * @return array<string, string>
     */
    private static function namedParagraphs(string $planName, int $dailyMinutes, bool $programReady, Teacher $teacher): array
    {
        // Paragraphes propres au professeur (accords au féminin) ; jamais un paragraphe en plus.
        $paragraphs = array_replace((array) config('teacher.welcome.paragraphs', []), array_intersect_key($teacher->welcomeParagraphs(), (array) config('teacher.welcome.paragraphs', [])));

        if ($programReady && isset($paragraphs['questionnaire'])) {
            $paragraphs['questionnaire'] = (string) config('teacher.welcome.program_ready');
        }

        $replace = [
            ':platform' => (string) config('platform.name'),
            ':teacher' => $teacher->name(),
            ':plan' => $planName,
            ':daily_duration' => Duration::format($dailyMinutes * 60),
            ':course_minutes' => Duration::format((int) config('platform.courses.max_session_minutes') * 60),
        ];

        $paragraphs = array_map(fn (string $text) => strtr($text, $replace), $paragraphs);

        // Teacher Rose : nombres dits en toutes lettres (« quinze minutes »), comme dans les vidéos de présentation ;
        // Teacher Joe : textes d'avant, rien n'est refait pour lui.
        return $teacher->isDefault()
            ? $paragraphs
            : array_map(fn (string $text) => (string) preg_replace_callback('/\b\d+\b/', fn (array $m) => PresentationScripts::words((int) $m[0]), $text), $paragraphs);
    }

    /**
     * Les messages communs à préparer : un par formule (la formule recommandée d'abord), programme à
     * construire puis déjà prêt. Les paragraphes identiques d'un message à l'autre partagent leur fichier.
     *
     * @return list<VoiceMessage>
     */
    public static function messages(): array
    {
        // En alternance, professeur par professeur (formule recommandée d'abord) : si la limite du jour du fournisseur
        // arrive en cours de préparation, chaque professeur a déjà son message principal (avant le 31 octobre 2026,
        // tous ceux de Teacher Joe passaient d'abord et Teacher Rose pouvait rester sans voix).
        $lists = array_values(array_map(fn (Teacher $teacher) => self::messagesFor($teacher), Teachers::choosable()));
        $messages = [];

        for ($index = 0, $count = max(array_map('count', $lists) ?: [0]); $index < $count; $index++) {
            foreach ($lists as $list) {
                if (isset($list[$index])) {
                    $messages[] = $list[$index];
                }
            }
        }

        return $messages;
    }

    /**
     * Les messages communs d'un professeur.
     *
     * @return list<VoiceMessage>
     */
    public static function messagesFor(Teacher $teacher): array
    {
        $plans = Plan::all();
        uasort($plans, fn (Plan $a, Plan $b) => (int) $b->recommended <=> (int) $a->recommended);
        $messages = [];

        foreach ([false, true] as $programReady) {
            foreach ($plans as $plan) {
                $pieces = [];
                $position = 0;

                foreach (self::namedParagraphs($plan->name, $plan->dailyMinutes, $programReady, $teacher) as $name => $text) {
                    $position++;
                    $name = $programReady && $name === 'questionnaire' ? 'program_ready' : $name;
                    $pieces[] = ['label' => "Bienvenue, paragraphe {$position} ({$name}, {$plan->name})".self::of($teacher), 'text' => $text];
                }

                $messages[] = self::voiceMessage(
                    'welcome:'.($teacher->isDefault() ? '' : $teacher->key.':').$plan->key.($programReady ? ':program' : ''),
                    'Bienvenue, formule '.$plan->name.($programReady ? ', programme déjà prêt' : '').self::of($teacher),
                    $pieces,
                    $teacher,
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
            $pieces[] = ['label' => 'Bienvenue, paragraphe '.($position + 1).' ('.$texts['plan'].')'.self::of($texts['teacher']), 'text' => $text];
        }

        return self::voiceMessage('welcome', 'Bienvenue, formule '.$texts['plan'].self::of($texts['teacher']), $pieces, $texts['teacher']);
    }

    /** « (Teacher Rose) » dans les libellés des journaux ; rien pour le professeur par défaut. */
    private static function of(Teacher $teacher): string
    {
        return $teacher->isDefault() ? '' : ' ('.$teacher->name().')';
    }

    /** Ton du message de bienvenue pour ce professeur (Teacher Joe : celui de la configuration). */
    public static function style(Teacher $teacher): string
    {
        return $teacher->speechStyle((string) config('teacher.welcome.style'));
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
    private static function voiceMessage(string $key, string $label, array $pieces, Teacher $teacher): VoiceMessage
    {
        return new VoiceMessage($key, $label, $pieces, self::style($teacher), fn (string $text, SpeechProvider $provider) => self::path($text, $provider), $teacher->isDefault() ? null : $teacher);
    }

    /**
     * Paragraphes communs, sans doublon, dans l'ordre des messages. Le nom sert aux journaux.
     *
     * @return list<array{label: string, text: string}>
     */
    public static function commonParagraphs(?Teacher $teacher = null): array
    {
        $pieces = [];

        foreach (self::messagesFor($teacher ?? Teachers::default()) as $message) {
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

        // Tous les professeurs de la liste, même pas encore proposés : leurs fichiers ne sont jamais effacés.
        foreach (Teachers::all() as $teacher) {
            foreach (SpeechProviders::all($teacher) as $provider) {
                foreach (self::commonParagraphs($teacher) as $piece) {
                    $paths[] = self::path($piece['text'], $provider);
                }
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
     * Fait préparer la salutation de cet apprenant (son prénom) APRÈS la réponse, dès l'ouverture d'un accès : à
     * la page de bienvenue, tout le message est prêt. Jamais d'erreur visible.
     */
    public static function queueOpening(User $user): void
    {
        if ($user->welcomed_at !== null) {
            return;
        }

        self::queueOpeningFor($user);
    }

    /**
     * Fait préparer APRÈS la réponse la salutation au prénom (Bonjour ou Bonsoir donné, professeur donné, sinon ceux
     * du moment et de l'apprenant) si elle n'est pas déjà gardée : ouverture de la page de bienvenue, choix d'un
     * autre professeur. Jamais pendant l'écoute, jamais d'erreur visible.
     */
    public static function queueOpeningFor(User $user, ?string $period = null, ?Teacher $teacher = null): void
    {
        if (SpeechProviders::available() === []) {
            return;
        }

        $id = $user->getKey();
        $teacherKey = $teacher?->key;

        defer(static function () use ($id, $period, $teacherKey) {
            try {
                // Relu : l'accès vient peut-être d'être ouvert (relations déjà chargées sans lui).
                $user = User::query()->find($id);
                $texts = $user === null ? null : self::texts($user, $period, $teacherKey !== null ? Teachers::find($teacherKey) : null);
                $provider = $texts === null ? null : self::providerFor($texts);

                if ($provider !== null) {
                    app(self::class)->get($texts['opening'], $provider, opening: true);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * La salutation au prénom est-elle déjà gardée chez ce fournisseur ?
     */
    public static function openingReady(array $texts, SpeechProvider $provider): bool
    {
        try {
            return PrivateStorage::disk()->exists(self::path($texts['opening'], $provider->forTeacher($texts['teacher']), true));
        } catch (\Throwable) {
            return false;
        }
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
            $provider->model(), $provider->voice(), $text, self::style($provider->teacher()), $provider->settings(),
        ], fn ($part) => $part !== ''))), 0, 20);

        return self::DIRECTORY.'/'.($opening ? self::OPENINGS.'/' : '').$provider->name().'-'.$provider->voice().'-'.$hash.'.wav';
    }

    /**
     * Le fichier WAV d'un morceau chez le fournisseur du message, ou null s'il n'est pas disponible. Pendant
     * l'écoute, seule l'ouverture (salutation et prénom, courte) est générée, par ce même fournisseur ; les
     * paragraphes communs sont préparés à l'avance (commande app:prepare-voice, bouton de « Diagnostic de la
     * voix ») : un paragraphe pas encore prêt reste affiché en texte, sans aucune demande.
     */
    public function get(string $text, SpeechProvider $provider, bool $opening = false, bool $readyOnly = false): ?string
    {
        $path = self::path($text, $provider, $opening);
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        // « readyOnly » : la page précharge sans jamais attendre une synthèse (la salutation se prépare à part).
        if (! $opening || $readyOnly || ! $provider->hasKey()) {
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
                    $wav = $provider->synthesize($text, self::style($provider->teacher()), 'Bienvenue, salutation'.self::of($provider->teacher()), (int) config('services.gemini.tts_listen_timeout'));
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
