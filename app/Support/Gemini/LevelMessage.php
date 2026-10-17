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
     * Le fichier WAV du message, généré au besoin ; null s'il n'est pas disponible.
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
        try {
            return Cache::lock('level-message:'.$path, 40)->block(35, fn () => $this->generate($level, $voice, $path, $failed));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function generate(Level $level, string $voice, string $path, string $failed): ?string
    {
        $disk = PrivateStorage::disk();

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        try {
            $wav = $this->sampler->synthesize($voice, self::text($level), (string) config('teacher.level_messages.style'));
        } catch (GeminiUnavailable $e) {
            report($e);
            Cache::put($failed, true, self::RETRY_AFTER);

            return null;
        }

        $disk->put($path, $wav);

        return $wav;
    }
}
