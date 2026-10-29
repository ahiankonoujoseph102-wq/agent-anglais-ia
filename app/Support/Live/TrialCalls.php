<?php

namespace App\Support\Live;

use Illuminate\Support\Facades\Cache;

/**
 * Appels OpenAI ouverts hors séance (essai de 30 secondes du diagnostic, « Écouter en séance » de « Essai des voix ») :
 * le serveur les raccroche lui-même une fois leur durée passée (live:close-stale, chaque minute), même si la page
 * a été fermée sans rien dire.
 */
class TrialCalls
{
    private const KEY = 'live-trial-calls';

    public static function remember(string $callId, int $endsAt): void
    {
        $calls = (array) Cache::get(self::KEY, []);
        $calls[$callId] = $endsAt;
        Cache::put(self::KEY, $calls, now()->addHour());
    }

    public static function forget(string $callId): void
    {
        $calls = (array) Cache::get(self::KEY, []);
        unset($calls[$callId]);
        Cache::put(self::KEY, $calls, now()->addHour());
    }

    /** Raccroche les appels dont la durée est passée ; renvoie leur nombre. */
    public static function hangUpOverdue(OpenAiRealtime $openai): int
    {
        $calls = (array) Cache::get(self::KEY, []);
        $done = 0;

        foreach ($calls as $callId => $endsAt) {
            if ((int) $endsAt <= now()->getTimestamp()) {
                $openai->hangup((string) $callId);
                unset($calls[$callId]);
                $done++;
            }
        }

        Cache::put(self::KEY, $calls, now()->addHour());

        return $done;
    }
}
