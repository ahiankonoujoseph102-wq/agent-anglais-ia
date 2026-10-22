<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * « Enregistrer ma séance » (config/platform.php → recording) : réglages donnés au navigateur. La vidéo est
 * fabriquée et gardée sur le téléphone de l'apprenant ; le serveur ne reçoit rien.
 */
final class SessionRecording
{
    /** Bouton proposé pour ce type de conversation ? */
    public static function enabled(string $mode): bool
    {
        return (bool) config('platform.recording.enabled')
            && in_array($mode, (array) config('platform.recording.modes', []), true);
    }

    /** Mention en bas de la vidéo : le nom de domaine de APP_URL (« talkami.laravel.cloud »). */
    public static function mention(): string
    {
        return (string) (parse_url(Seo::base(), PHP_URL_HOST) ?: '');
    }

    /**
     * Réglages lus par public/js/session-recorder.js.
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(string $mode): array
    {
        $platform = (string) config('platform.name');

        return [
            'width' => (int) config('platform.recording.width'),
            'height' => (int) config('platform.recording.height'),
            'fps' => (int) config('platform.recording.fps'),
            'video_bits_per_second' => (int) config('platform.recording.video_bits_per_second'),
            'audio_bits_per_second' => (int) config('platform.recording.audio_bits_per_second'),
            'mention' => self::mention(),
            'platform' => $platform,
            'teacher' => (string) config('avatar.model.name'),
            'title' => $mode === 'assessment' ? 'Évaluation gratuite' : 'Séance du jour',
            // Début du nom du fichier : « talkami-evaluation », « talkami-seance ».
            'file_prefix' => Str::slug($platform).'-'.($mode === 'assessment' ? 'evaluation' : 'seance'),
        ];
    }
}
