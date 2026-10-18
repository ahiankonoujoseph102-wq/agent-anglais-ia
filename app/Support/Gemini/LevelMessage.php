<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechFiles;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\VoiceMessage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Message vocal du résultat de l'évaluation : quelques phrases en français de Teacher Joe,
 * une par niveau (config/teacher.php → level_messages). Généré une seule fois par niveau, par fournisseur
 * et par voix, puis gardé sur le disque privé (le nom du fichier porte le fournisseur et la voix).
 * En cas d'échec, aucune nouvelle demande pendant quelques minutes : la page reste utilisable sans lui.
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
        $hash = substr(sha1(implode('|', [
            $provider->model(), $provider->voice(), self::text($level), config('teacher.level_messages.style'),
        ])), 0, 16);

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

    /**
     * Le message d'un niveau (un seul morceau).
     */
    public static function message(Level $level): VoiceMessage
    {
        return new VoiceMessage(
            'level:'.$level->value,
            self::label($level),
            [['label' => self::label($level), 'text' => self::text($level)]],
            (string) config('teacher.level_messages.style'),
            fn (string $text, SpeechProvider $provider) => self::path($level, $provider),
        );
    }

    /**
     * @return list<VoiceMessage>
     */
    public static function messages(): array
    {
        return array_map(fn (array $item) => self::message($item['level']), self::all());
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
    public function get(Level $level): ?string
    {
        $message = self::message($level);
        $disk = PrivateStorage::disk();
        $provider = $message->provider();

        if ($provider !== null && $message->isCompleteAt($provider)) {
            return $disk->get(self::path($level, $provider));
        }

        foreach (SpeechProviders::available() as $provider) {
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
