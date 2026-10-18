<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Support\PrivateStorage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Message vocal du résultat de l'évaluation : quelques phrases en français de Teacher Joe,
 * une par niveau (config/teacher.php → level_messages), dites avec la voix choisie.
 * Généré une seule fois par niveau et par voix, puis gardé sur le disque privé.
 * En cas d'échec, aucune nouvelle demande pendant quelques minutes : la page reste utilisable sans lui.
 */
class LevelMessage
{
    public const DIRECTORY = 'level-messages';

    /** Pause après un échec, en secondes (aucune nouvelle demande à Google pendant ce temps). */
    public const RETRY_AFTER = 600;

    public function __construct(private VoiceSampler $sampler) {}

    public static function text(Level $level): string
    {
        return (string) config("teacher.level_messages.texts.{$level->value}");
    }

    /**
     * Chemin du message : change si la voix, le modèle, le texte ou le ton changent.
     */
    public static function path(Level $level, string $voice): string
    {
        $hash = substr(sha1(implode('|', [
            config('services.gemini.tts_model'), $voice, self::text($level), config('teacher.level_messages.style'),
        ])), 0, 16);

        return self::DIRECTORY.'/'.$level->value.'-'.$voice.'-'.$hash.'.wav';
    }

    /**
     * Messages à préparer avant toute écoute : un par niveau, avec la voix actuelle.
     *
     * @return list<array{level: Level, label: string, text: string}>
     */
    public static function all(): array
    {
        return array_map(fn (Level $level) => [
            'level' => $level,
            'label' => 'Résultat de l\'évaluation, niveau '.$level->value,
            'text' => self::text($level),
        ], array_values(array_filter(Level::cases(), fn (Level $level) => self::text($level) !== '')));
    }

    public static function isReady(Level $level): bool
    {
        try {
            return PrivateStorage::disk()->exists(self::path($level, (string) TeacherVoice::current()));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Messages prêts sur le total.
     *
     * @return array{ready: int, total: int}
     */
    public static function status(): array
    {
        $all = self::all();

        return [
            'ready' => count(array_filter($all, fn (array $item) => self::isReady($item['level']))),
            'total' => count($all),
        ];
    }

    /**
     * Le fichier WAV du message, généré au besoin (normalement déjà préparé à l'avance) ; null s'il n'est
     * pas disponible : la page affiche alors le texte.
     */
    public function get(Level $level): ?string
    {
        $voice = (string) TeacherVoice::current();
        $path = self::path($level, $voice);
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $failed = 'level-message-failed:'.$path;

        if (Cache::has($failed)) {
            return null;
        }

        // Un seul appel à la fois pour un même message (deux apprenants qui touchent le bouton ensemble).
        $wait = (int) config('services.gemini.tts_listen_timeout') + 5;

        try {
            return Cache::lock('level-message:'.$path, $wait + 5)->block($wait, function () use ($level, $path, $failed, $disk) {
                if ($disk->exists($path)) {
                    return $disk->get($path);
                }

                try {
                    return $this->prepare($level, (int) config('services.gemini.tts_listen_timeout'));
                } catch (GeminiUnavailable) {
                    // Cause déjà journalisée ; aucune nouvelle demande pendant quelques minutes.
                    Cache::put($failed, true, self::RETRY_AFTER);

                    return null;
                }
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /**
     * Génère et garde le message d'un niveau. Renvoie le fichier WAV.
     *
     * @throws SpeechFailed
     */
    public function prepare(Level $level, ?int $timeout = null): string
    {
        $voice = (string) TeacherVoice::current();
        $label = 'Résultat de l\'évaluation, niveau '.$level->value;
        $wav = $this->sampler->synthesize($voice, self::text($level), (string) config('teacher.level_messages.style'), $label, $timeout);

        VoiceSampler::store(self::path($level, $voice), $wav, $label);

        return $wav;
    }
}
