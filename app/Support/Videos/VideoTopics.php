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

    /**
     * Les prochains sujets jamais traités, dans l'ordre du programme.
     *
     * @return list<array{key: string, level: string, title: string, angle: string}>
     */
    public static function next(int $count): array
    {
        $used = LessonVideo::query()->pluck('topic_key')->flip();

        return array_slice(array_values(array_filter(self::all(), fn (array $topic) => ! isset($used[$topic['key']]))), 0, max(0, $count));
    }

    /** Sujets encore jamais traités. */
    public static function remaining(): int
    {
        $used = LessonVideo::query()->pluck('topic_key')->flip();

        return count(array_filter(self::all(), fn (array $topic) => ! isset($used[$topic['key']])));
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
