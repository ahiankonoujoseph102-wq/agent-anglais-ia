<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechFiles;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\VoiceMessage;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Message vocal du résultat de l'évaluation : quelques phrases en français de Teacher Joe,
 * une par niveau (config/teacher.php → level_messages). Généré une seule fois par niveau, par fournisseur
 * et par voix, puis gardé sur le disque privé (le nom du fichier porte le fournisseur et la voix).
 * En cas d'échec, aucune nouvelle demande pendant quelques minutes : la page reste utilisable sans lui.
 * Un message par professeur (sa voix, son ton) ; les fichiers de Teacher Joe gardent leur nom d'avant.
 */
class LevelMessage
{
    public const DIRECTORY = 'level-messages';

    /** Pause après un échec, en secondes (aucune nouvelle demande pendant ce temps). */
    public const RETRY_AFTER = 600;

    public static function text(Level $level): string
    {
        return (string) config("teacher.level_messages.texts.{$level->value}");
    }

    public static function label(Level $level): string
    {
        return 'Résultat de l\'évaluation, niveau '.$level->value;
    }

    /**
     * Chemin du message : porte le fournisseur et la voix ; change si le fournisseur, la voix, le modèle,
     * le texte ou le ton changent.
     */
    public static function path(Level $level, SpeechProvider $provider): string
    {
        return self::DIRECTORY.'/'.$level->value.'-'.$provider->name().'-'.self::legacyName($level, $provider);
    }

    /**
     * Fin du nom : « {voix}-{empreinte}.wav » (nom complet des fichiers de Gemini d'avant le choix du fournisseur).
     */
    private static function legacyName(Level $level, SpeechProvider $provider): string
    {
        $hash = substr(sha1(implode('|', array_filter([
            $provider->model(), $provider->voice(), self::text($level), self::style($provider->teacher()), $provider->settings(),
        ], fn ($part) => $part !== ''))), 0, 16);

        return $provider->voice().'-'.$hash.'.wav';
    }

    /**
     * Messages à préparer avant toute écoute : un par niveau.
     *
     * @return list<array{level: Level, label: string, text: string}>
     */
    public static function all(): array
    {
        return array_map(fn (Level $level) => [
            'level' => $level,
            'label' => self::label($level),
            'text' => self::text($level),
        ], array_values(array_filter(Level::cases(), fn (Level $level) => self::text($level) !== '')));
    }

    /** Ton du message pour ce professeur (Teacher Joe : celui de la configuration). */
    public static function style(Teacher $teacher): string
    {
        return $teacher->speechStyle((string) config('teacher.level_messages.style'));
    }

    /**
     * Le message d'un niveau (un seul morceau), dit par ce professeur (null : professeur par défaut).
     */
    public static function message(Level $level, ?Teacher $teacher = null): VoiceMessage
    {
        $teacher ??= Teachers::default();
        $label = self::label($level).($teacher->isDefault() ? '' : ' ('.$teacher->name().')');

        return new VoiceMessage(
            'level:'.($teacher->isDefault() ? '' : $teacher->key.':').$level->value,
            $label,
            [['label' => $label, 'text' => self::text($level)]],
            self::style($teacher),
            fn (string $text, SpeechProvider $provider) => self::path($level, $provider),
            $teacher->isDefault() ? null : $teacher,
        );
    }

    /**
     * Messages à préparer : un par niveau et par professeur proposé.
     *
     * @return list<VoiceMessage>
     */
    public static function messages(): array
    {
        $messages = [];

        foreach (Teachers::choosable() as $teacher) {
            foreach (self::all() as $item) {
                $messages[] = self::message($item['level'], $teacher);
            }
        }

        return $messages;
    }

    public static function isReady(Level $level, SpeechProvider $provider): bool
    {
        try {
            return PrivateStorage::disk()->exists(self::path($level, $provider));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Le message de ce niveau est-il prêt à être joué tout de suite (fichier gardé chez son fournisseur) ?
     */
    public static function ready(Level $level, ?Teacher $teacher = null): bool
    {
        try {
            $message = self::message($level, $teacher);
            $provider = $message->provider();

            return $provider !== null && $message->isCompleteAt($provider);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fait préparer le message de ce niveau APRÈS la réponse (verdict de l'évaluation, page de résultat), s'il
     * manque : l'apprenant le trouve prêt. Jamais d'erreur visible.
     */
    public static function queue(Level $level, ?Teacher $teacher = null): void
    {
        if (self::ready($level, $teacher) || SpeechProviders::available() === []) {
            return;
        }

        defer(static function () use ($level, $teacher) {
            try {
                app(self::class)->get($level, $teacher);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Renomme les messages de Gemini gardés avant le choix du fournisseur : ils ne sont pas redemandés.
     */
    public static function adoptLegacyFiles(): int
    {
        $disk = PrivateStorage::disk();
        $gemini = SpeechProviders::find('gemini');
        $files = SpeechFiles::index([self::DIRECTORY]);
        $adopted = 0;

        foreach (self::all() as $item) {
            $old = self::DIRECTORY.'/'.$item['level']->value.'-'.self::legacyName($item['level'], $gemini);
            $new = self::path($item['level'], $gemini);

            if (isset($files[$old]) && ! isset($files[$new])) {
                $disk->move($old, $new);
                $adopted++;
            }
        }

        return $adopted;
    }

    /**
     * Le fichier WAV du message, généré au besoin (normalement déjà préparé à l'avance) ; null s'il n'est
     * pas disponible : la page affiche alors le texte. Un seul morceau : il est dit en entier par UN fournisseur,
     * celui qui l'a déjà, sinon le premier, dans l'ordre réglé, qui réussit à le générer.
     */
    public function get(Level $level, ?Teacher $teacher = null): ?string
    {
        $message = self::message($level, $teacher);
        $disk = PrivateStorage::disk();
        $provider = $message->provider();

        if ($provider !== null && $message->isCompleteAt($provider)) {
            return $disk->get(self::path($level, $provider));
        }

        foreach (SpeechProviders::available($message->teacher) as $provider) {
            $wav = $this->generate($message, $level, $provider);

            if ($wav !== null) {
                return $wav;
            }
        }

        return null;
    }

    private function generate(VoiceMessage $message, Level $level, SpeechProvider $provider): ?string
    {
        $path = self::path($level, $provider);
        $failed = 'level-message-failed:'.$path;
        $disk = PrivateStorage::disk();

        if (Cache::has($failed)) {
            return null;
        }

        // Un seul appel à la fois pour un même message (deux apprenants qui touchent le bouton ensemble).
        $wait = (int) config('services.gemini.tts_listen_timeout') + 5;

        try {
            return Cache::lock('level-message:'.$path, $wait + 5)->block($wait, function () use ($message, $provider, $path, $failed, $disk) {
                if ($disk->exists($path)) {
                    return $disk->get($path);
                }

                try {
                    return $message->prepare($message->pieces[0], $provider, (int) config('services.gemini.tts_listen_timeout'));
                } catch (GeminiUnavailable) {
                    // Cause déjà journalisée ; aucune nouvelle demande à ce fournisseur pendant quelques minutes.
                    Cache::put($failed, true, self::RETRY_AFTER);

                    return null;
                }
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }
}
