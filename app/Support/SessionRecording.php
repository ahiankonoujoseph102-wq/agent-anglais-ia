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
            'board_share' => (float) config('platform.recording.board_share'),
            'transition_ms' => (int) config('platform.recording.transition_ms'),
            'board_sizes' => array_map('intval', (array) config('platform.recording.board_sizes')),
            'camera_full' => config('platform.recording.camera_full'),
            'camera_split' => config('platform.recording.camera_split'),
            'frame_budget_ms' => (int) config('platform.recording.frame_budget_ms'),
            'teacher_wait_ms' => (int) config('platform.recording.teacher_wait_ms'),
            'mention' => self::mention(),
            'platform' => $platform,
            // Début du nom du fichier : « talkami-evaluation », « talkami-seance ».
            'file_prefix' => Str::slug($platform).'-'.($mode === 'assessment' ? 'evaluation' : 'seance'),
            // Évaluation : tableau neutre avant le verdict, puis le niveau (null ailleurs).
            'assessment' => $mode === 'assessment' ? self::assessmentBoard() : null,
        ];
    }

    /**
     * Textes du tableau de l'évaluation dans la vidéo, marqueurs remplis. Aucun niveau n'y figure : il n'arrive que par
     * le résultat enregistré (/evaluation/resultat), au même instant qu'à l'écran.
     *
     * @return array<string, mixed>
     */
    public static function assessmentBoard(): array
    {
        $board = (array) config('platform.recording.assessment_board');
        $fill = fn (string $text): string => strtr($text, [
            ':teacher' => (string) config('avatar.model.name'),
            ':minutes' => (string) (int) config('platform.assessment.max_minutes'),
        ]);

        return [
            'phase' => $fill((string) $board['phase']),
            'title' => $fill((string) $board['title']),
            'text' => $fill((string) $board['text']),
            'result_phase' => $fill((string) $board['result_phase']),
            'level_heading' => $fill((string) $board['level_heading']),
            'level_text' => $fill((string) $board['level_text']),
            'reveal_ms' => (int) $board['reveal_ms'],
        ];
    }
}
