<?php

namespace App\Support\Gemini;

use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

/**
 * Prépare à l'avance les messages vocaux communs (paragraphes du message de bienvenue, puis messages du
 * résultat de l'évaluation) : un par un, en espaçant les demandes (limite par minute du mode gratuit de
 * Gemini), en réessayant après une erreur 429 avec un délai croissant, sans jamais dépasser le temps accordé.
 * Ce qui ne tient pas dans ce temps reste à faire : il suffit de relancer (commande app:prepare-voice,
 * planificateur, bouton « Préparer le message de bienvenue »).
 */
class SpeechPreparer
{
    /** Temps minimal pour lancer une demande : en dessous, on s'arrête et on dira ce qui reste. */
    public const MIN_REQUEST_SECONDS = 10;

    /** Échecs (hors 429) tolérés avant de s'arrêter. */
    public const MAX_FAILURES = 3;

    public function __construct(private WelcomeMessage $welcome, private LevelMessage $levels) {}

    /**
     * Morceaux encore à préparer, dans l'ordre : le message de bienvenue d'abord.
     *
     * @return list<array{label: string, run: \Closure(int): mixed}>
     */
    public function missing(): array
    {
        $items = [];

        foreach (WelcomeMessage::commonParagraphs() as $piece) {
            if (! WelcomeMessage::isReady($piece['text'])) {
                $items[] = ['label' => $piece['label'], 'run' => fn (int $timeout) => $this->welcome->prepare($piece, $timeout)];
            }
        }

        foreach (LevelMessage::all() as $item) {
            if (! LevelMessage::isReady($item['level'])) {
                $items[] = ['label' => $item['label'], 'run' => fn (int $timeout) => $this->levels->prepare($item['level'], $timeout)];
            }
        }

        return $items;
    }

    /**
     * @param  int  $budget  temps accordé, en secondes
     * @param  (\Closure(string): void)|null  $report  reçoit une ligne en français par étape
     * @return array{generated: int, stopped: ?string, error: ?string, welcome: array{ready: int, total: int}, levels: array{ready: int, total: int}}
     */
    public function run(int $budget, ?\Closure $report = null): array
    {
        $report ??= fn (string $line) => null;
        $started = Carbon::now();
        $remaining = fn () => $budget - $started->diffInSeconds(Carbon::now(), true);
        $spacing = (int) config('services.gemini.tts_spacing_seconds');
        $delays = array_values((array) config('services.gemini.tts_retry_delays'));
        $generated = 0;
        $failures = 0;
        $requested = false;
        $stopped = null;
        $error = null;
        $retryAfter = null;

        foreach ($this->missing() as $item) {
            $attempt = 0;

            while (true) {
                $wait = $requested ? $spacing : 0;

                if ($attempt > 0) {
                    $wait = max($wait, $delays[$attempt - 1], $retryAfter ?? 0);
                }

                $timeout = (int) min((int) config('services.gemini.tts_timeout'), floor($remaining() - $wait));

                if ($timeout < self::MIN_REQUEST_SECONDS) {
                    $stopped = 'budget';
                    break 2;
                }

                if ($wait > 0) {
                    Sleep::for($wait)->seconds();
                }

                $requested = true;

                try {
                    ($item['run'])($timeout);
                    $generated++;
                    $report("Prêt : {$item['label']}.");
                    break;
                } catch (SpeechFailed $e) {
                    $error = $e->reason().($e->status ? " (HTTP {$e->status})" : '').' : '.$e->getMessage();
                    $report("Échec : {$item['label']} : {$error}");

                    if (in_array($e->kind, [SpeechFailed::STORAGE, SpeechFailed::QUOTA_DAILY, SpeechFailed::MISSING_KEY], true)) {
                        $stopped = $e->kind;
                        break 2;
                    }

                    if ($e->isRetryable()) {
                        $retryAfter = $e->retryAfter;

                        if (++$attempt > count($delays)) {
                            $stopped = SpeechFailed::QUOTA;
                            break 2;
                        }

                        continue;
                    }

                    if (++$failures >= self::MAX_FAILURES) {
                        $stopped = 'errors';
                        break 2;
                    }

                    break;
                }
            }

            $retryAfter = null;
        }

        // Les versions qui ne servent plus (phrase corrigée, ancien message, autre voix) sont effacées.
        try {
            WelcomeMessage::purgeOutdated();
        } catch (\Throwable) {
            // Rien de grave : elles le seront à la prochaine préparation.
        }

        return [
            'generated' => $generated,
            'stopped' => $stopped,
            'error' => $error,
            'welcome' => WelcomeMessage::status(),
            'levels' => LevelMessage::status(),
        ];
    }

    /**
     * Résumé en français d'une préparation.
     *
     * @param  array{generated: int, stopped: ?string, error: ?string, welcome: array{ready: int, total: int}, levels: array{ready: int, total: int}}  $result
     */
    public static function summary(array $result): string
    {
        $welcome = $result['welcome'];
        $levels = $result['levels'];
        $left = ($welcome['total'] - $welcome['ready']) + ($levels['total'] - $levels['ready']);
        $lines = [
            "Message de bienvenue : {$welcome['ready']} paragraphes prêts sur {$welcome['total']}.",
            "Messages du résultat de l'évaluation : {$levels['ready']} prêts sur {$levels['total']}.",
        ];

        if ($left === 0) {
            $lines[] = 'Tout est prêt.';

            return implode(' ', $lines);
        }

        $lines[] = match ($result['stopped']) {
            'budget' => "Il en reste {$left} : le temps accordé est écoulé, relancez la préparation.",
            SpeechFailed::QUOTA => "Il en reste {$left} : Google limite le nombre de demandes par minute (erreur 429). Relancez dans quelques minutes.",
            SpeechFailed::QUOTA_DAILY => "Il en reste {$left} : la limite de demandes du jour de Google est atteinte (erreur 429). Relancez demain.",
            SpeechFailed::MISSING_KEY => "Il en reste {$left} : la clé GEMINI_API_KEY est absente.",
            SpeechFailed::STORAGE => "Il en reste {$left} : l'écriture dans le stockage privé a échoué.",
            default => "Il en reste {$left}.".($result['error'] ? ' Dernière erreur : '.$result['error'] : ''),
        };

        return implode(' ', $lines);
    }
}
