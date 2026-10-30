<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;

/**
 * Programme des vidéos (resources/videos/topics.php) : du plus simple au plus avancé, chaque sujet une seule fois.
 */
class VideoTopics
{
    /**
     * @return list<array{key: string, level: string, title: string, angle: string}>
     */
    public static function all(): array
    {
        return array_values(require resource_path('videos/topics.php'));
    }

    /** Niveau des vidéos de promotion (config videos.promo_level) ; null : tous les niveaux, dans l'ordre. */
    public static function level(): ?string
    {
        $level = config('videos.promo_level');

        return is_string($level) && $level !== '' ? $level : null;
    }

    /**
     * Les prochains sujets jamais traités du niveau des vidéos de promotion, dans l'ordre du programme. Plus aucun
     * sujet de ce niveau : rien (aucun sujet d'un autre niveau n'est pris à sa place).
     *
     * @return list<array{key: string, level: string, title: string, angle: string}>
     */
    public static function next(int $count): array
    {
        return array_slice(self::unused(), 0, max(0, $count));
    }

    /** Sujets de ce niveau encore jamais traités. */
    public static function remaining(): int
    {
        return count(self::unused());
    }

    /** @return list<array{key: string, level: string, title: string, angle: string}> */
    private static function unused(): array
    {
        $used = LessonVideo::query()->pluck('topic_key')->flip();
        $level = self::level();

        return array_values(array_filter(self::all(), fn (array $topic) => ! isset($used[$topic['key']]) && ($level === null || $topic['level'] === $level)));
    }

    /** @return array{key: string, level: string, title: string, angle: string}|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $topic) {
            if ($topic['key'] === $key) {
                return $topic;
            }
        }

        return null;
    }
}
