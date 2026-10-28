<?php

namespace App\Support\Live;

use App\Models\ConversationEvent;
use App\Models\ConversationLine;
use Illuminate\Support\Carbon;

/**
 * Rapidité de Teacher Joe, jour par jour (heure de l'application, Lomé) : délai de réponse (fin de la parole de
 * l'élève → premier son de sa réponse, mesuré dans le navigateur : conversation_lines.metrics.delay_ms) et incidents
 * des séances (conversation_events). Page « Consommation », partie « Rapidité de Teacher Joe » (#rapidite) : pour voir
 * si un réglage améliore vraiment les choses.
 */
final class Responsiveness
{
    /** Au-delà, une réponse est comptée comme lente (même seuil que « Lire la conversation »). */
    public static function slowMs(): int
    {
        return (int) config('platform.transcripts.slow_reply_seconds', 5) * 1000;
    }

    /** Incidents comptés par jour, dans cet ordre (libellés : ConversationEvent::KINDS). */
    public const EVENTS = ['reconnect', 'provider_error', 'no_answer', 'auto_nudge', 'auto_reconnect', 'switch', 'nudge'];

    /**
     * Une ligne par jour, du plus récent au plus ancien (jours sans aucune mesure ni incident : absents).
     *
     * @return list<array{day: string, count: int, average: int|null, median: int|null, p90: int|null, worst: int|null, slow: int, by_type: array<string, int>, events: array<string, int>}>
     */
    public static function daily(int $days = 14): array
    {
        $since = now()->startOfDay()->subDays($days - 1);
        $delays = [];
        $byType = [];

        ConversationLine::query()
            ->where('speaker', 'teacher')
            ->whereNotNull('metrics')
            ->where('created_at', '>=', $since)
            ->select(['id', 'speaker', 'conversation_type', 'metrics', 'spoken_at', 'created_at'])
            ->lazyById(2000)
            ->each(function (ConversationLine $line) use (&$delays, &$byType) {
                $delay = $line->delayMs();

                if ($delay === null || $delay < 0) {
                    return;
                }

                $day = self::day($line->spoken_at ?? $line->created_at);
                $delays[$day][] = $delay;
                $byType[$day][$line->conversation_type] = ($byType[$day][$line->conversation_type] ?? 0) + 1;
            });

        $events = [];

        ConversationEvent::query()
            ->whereIn('kind', self::EVENTS)
            ->where('happened_at', '>=', $since)
            ->select(['id', 'kind', 'happened_at'])
            ->lazyById(2000)
            ->each(function (ConversationEvent $event) use (&$events) {
                $day = self::day($event->happened_at);
                $events[$day][$event->kind] = ($events[$day][$event->kind] ?? 0) + 1;
            });

        $rows = [];

        foreach (array_unique([...array_keys($delays), ...array_keys($events)]) as $day) {
            $rows[] = ['day' => $day] + self::stats($delays[$day] ?? []) + [
                'by_type' => $byType[$day] ?? [],
                'events' => array_replace(array_fill_keys(self::EVENTS, 0), $events[$day] ?? []),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($b['day'], $a['day']));

        return $rows;
    }

    /**
     * Par type de conversation (évaluation, séance du jour, « Mes cours », bilan de niveau), sur les derniers jours.
     *
     * @return array<string, array{count: int, average: int|null, median: int|null, p90: int|null, worst: int|null, slow: int}>
     */
    public static function byType(int $days = 7): array
    {
        $values = array_fill_keys(array_keys(Transcripts::TYPES), []);

        ConversationLine::query()
            ->where('speaker', 'teacher')
            ->whereNotNull('metrics')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(['id', 'speaker', 'conversation_type', 'metrics'])
            ->lazyById(2000)
            ->each(function (ConversationLine $line) use (&$values) {
                $delay = $line->delayMs();

                if ($delay !== null && $delay >= 0 && isset($values[$line->conversation_type])) {
                    $values[$line->conversation_type][] = $delay;
                }
            });

        return array_map(fn (array $list) => self::stats($list), $values);
    }

    /**
     * @param  list<int>  $values  délais en millisecondes
     * @return array{count: int, average: int|null, median: int|null, p90: int|null, worst: int|null, slow: int}
     */
    public static function stats(array $values): array
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return ['count' => 0, 'average' => null, 'median' => null, 'p90' => null, 'worst' => null, 'slow' => 0];
        }

        $slow = self::slowMs();

        return [
            'count' => $count,
            'average' => (int) round(array_sum($values) / $count),
            'median' => (int) $values[intdiv($count - 1, 2)],
            'p90' => (int) $values[(int) ceil(0.9 * $count) - 1],
            'worst' => (int) $values[$count - 1],
            'slow' => count(array_filter($values, fn (int $value) => $value > $slow)),
        ];
    }

    private static function day(?Carbon $at): string
    {
        return ($at ?? now())->copy()->timezone((string) config('app.timezone'))->toDateString();
    }
}
