<?php

namespace App\Support\Gemini;

use App\Support\PrivateStorage;
use App\Support\Speech\Loudness;
use App\Support\Speech\SpeechFiles;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\VoiceMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Prépare à l'avance les messages vocaux communs (messages de bienvenue de chaque formule, puis messages du
 * résultat de l'évaluation), message par message, avec la règle d'UNE seule voix par message :
 * - un message déjà complet chez un fournisseur n'est plus touché ;
 * - sinon il est complété chez le premier fournisseur de l'ordre réglé (TTS_PROVIDERS) ;
 * - si ce fournisseur ne peut pas le compléter (limite du jour, crédit épuisé, 429 répétés, erreur, délai),
 *   le message ENTIER est préparé chez le fournisseur suivant (un fournisseur épuisé n'est plus appelé).
 * Les demandes sont espacées (limite par minute), une erreur 429 est réessayée après un délai croissant, et le
 * temps accordé n'est jamais dépassé : il suffit de relancer (commande app:prepare-voice, planificateur,
 * bouton « Préparer le message de bienvenue »).
 */
class SpeechPreparer
{
    /** Temps minimal pour lancer une demande : en dessous, on s'arrête et on dira ce qui reste. */
    public const MIN_REQUEST_SECONDS = 10;

    /** Échecs (hors 429 et limites du jour) tolérés avant de s'arrêter. */
    public const MAX_FAILURES = 3;

    /**
     * Tous les messages communs, dans l'ordre : le message de bienvenue d'abord.
     *
     * @return list<VoiceMessage>
     */
    public static function messages(): array
    {
        return [...WelcomeMessage::messages(), ...LevelMessage::messages()];
    }

    /**
     * État de chaque message : morceaux prêts chez chaque fournisseur, fournisseur qui le jouera, complet ou non.
     *
     * @return array{welcome: array{ready: int, total: int}, levels: array{ready: int, total: int}, messages: list<array{key: string, label: string, total: int, ready: array<string, int>, provider: ?string, complete: bool}>}
     */
    public static function status(): array
    {
        $files = SpeechFiles::index([WelcomeMessage::DIRECTORY, LevelMessage::DIRECTORY]);
        $status = ['welcome' => ['ready' => 0, 'total' => 0], 'levels' => ['ready' => 0, 'total' => 0], 'messages' => []];

        foreach (self::messages() as $message) {
            $ready = [];

            foreach (SpeechProviders::all() as $provider) {
                $ready[$provider->name()] = $message->readyCount($provider, $files);
            }

            $provider = $message->provider($files);
            $complete = $provider !== null && $ready[$provider->name()] === count($message->pieces);
            $group = str_starts_with($message->key, 'welcome') ? 'welcome' : 'levels';
            $status[$group]['total']++;
            $status[$group]['ready'] += (int) $complete;
            $status['messages'][] = [
                'key' => $message->key,
                'label' => $message->label,
                'total' => count($message->pieces),
                'ready' => $ready,
                'provider' => $provider?->name(),
                'complete' => $complete,
            ];
        }

        return $status;
    }

    /**
     * @param  int  $budget  temps accordé, en secondes
     * @param  (\Closure(string): void)|null  $report  reçoit une ligne en français par étape
     * @return array{generated: int, stopped: ?string, error: ?string, exhausted: list<string>, keys: list<string>, welcome: array{ready: int, total: int}, levels: array{ready: int, total: int}}
     */
    public function run(int $budget, ?\Closure $report = null): array
    {
        $report ??= fn (string $line) => null;
        $started = Carbon::now();
        $remaining = fn () => $budget - $started->diffInSeconds(Carbon::now(), true);
        $delays = array_values((array) config('services.gemini.tts_retry_delays'));
        $providers = SpeechProviders::available();
        $generated = 0;
        $failures = 0;
        $requested = false;
        $stopped = null;
        $error = null;
        /** @var array<string, string> $exhausted fournisseur => cause */
        $exhausted = [];

        // Paragraphes de Gemini gardés avant le choix du fournisseur : renommés, jamais redemandés.
        try {
            WelcomeMessage::adoptLegacyFiles();
            LevelMessage::adoptLegacyFiles();
        } catch (\Throwable) {
            // Rien de grave : ils seront simplement préparés de nouveau.
        }

        // Fichiers préparés avant la normalisation du volume : amenés une fois à la même crête.
        try {
            $normalized = self::normalizeStoredFiles();

            if ($normalized > 0) {
                $report("Volume des messages déjà préparés harmonisé : {$normalized} fichier(s).");
            }
        } catch (\Throwable) {
            // Rien de grave : nouvel essai à la prochaine préparation.
        }

        if ($providers === []) {
            $stopped = SpeechFailed::MISSING_KEY;
        }

        foreach ($providers === [] ? [] : self::messages() as $message) {
            $files = $message->files();

            foreach (SpeechProviders::configured() as $provider) {
                if ($message->isCompleteAt($provider, $files)) {
                    continue 2;
                }
            }

            foreach ($providers as $provider) {
                if (isset($exhausted[$provider->name()])) {
                    continue;
                }

                $failedHere = false;

                foreach ($message->missing($provider, $files) as $piece) {
                    $attempt = 0;
                    $retryAfter = null;

                    while (true) {
                        $wait = $requested ? $provider->spacing() : 0;

                        if ($attempt > 0) {
                            $wait = max($wait, $delays[$attempt - 1], $retryAfter ?? 0);
                        }

                        $timeout = (int) min((int) config('services.gemini.tts_timeout'), floor($remaining() - $wait));

                        if ($timeout < self::MIN_REQUEST_SECONDS) {
                            $stopped = 'budget';
                            break 4;
                        }

                        if ($wait > 0) {
                            Sleep::for($wait)->seconds();
                        }

                        $requested = true;

                        try {
                            $message->prepare($piece, $provider, $timeout);
                            $files[$message->path($piece['text'], $provider)] = true;
                            $generated++;
                            $report("Prêt : {$piece['label']} ({$provider->label()}).");
                            break;
                        } catch (SpeechFailed $e) {
                            $error = $provider->label().' : '.$e->reason().($e->status ? " (HTTP {$e->status})" : '').' : '.$e->getMessage();
                            $report("Échec : {$piece['label']} : {$error}");

                            if ($e->kind === SpeechFailed::STORAGE) {
                                $stopped = SpeechFailed::STORAGE;
                                break 4;
                            }

                            if ($e->isRetryable() && ++$attempt <= count($delays)) {
                                $retryAfter = $e->retryAfter;

                                continue;
                            }

                            if ($e->isRetryable() || $e->exhaustsProvider()) {
                                // Inutile d'insister aujourd'hui : les messages suivants passent au fournisseur suivant.
                                $exhausted[$provider->name()] = $e->kind;
                            } elseif (++$failures >= self::MAX_FAILURES) {
                                $stopped = 'errors';
                                break 4;
                            }

                            $failedHere = true;
                            break 2;
                        }
                    }
                }

                if (! $failedHere) {
                    continue 2;
                }

                // Une seule voix par message : le suivant, s'il y en a un, prépare le message en entier.
                $report("Message « {$message->label} » non complété par {$provider->label()}.");
            }

            if (count($exhausted) === count($providers)) {
                $stopped = end($exhausted);
                break;
            }
        }

        // Les versions qui ne servent plus (phrase corrigée, ancien message, autre voix) sont effacées.
        try {
            WelcomeMessage::purgeOutdated();
        } catch (\Throwable) {
            // Rien de grave : elles le seront à la prochaine préparation.
        }

        $status = self::status();

        return [
            'generated' => $generated,
            'stopped' => $stopped,
            'error' => $error,
            'exhausted' => array_map(fn (string $name) => SpeechProviders::find($name)->label(), array_keys($exhausted)),
            'keys' => array_map(fn (SpeechProvider $provider) => $provider->keyVariable(), SpeechProviders::configured()),
            'welcome' => $status['welcome'],
            'levels' => $status['levels'],
        ];
    }

    /**
     * Résumé en français d'une préparation.
     *
     * @param  array{generated: int, stopped: ?string, error: ?string, exhausted?: list<string>, keys?: list<string>, welcome: array{ready: int, total: int}, levels: array{ready: int, total: int}}  $result
     */
    /**
     * Amène une fois les messages déjà gardés à la crête de Loudness (fichiers d'avant la normalisation).
     */
    public static function normalizeStoredFiles(): int
    {
        $key = 'speech-loudness:'.Loudness::targetDb();

        if (Cache::get($key)) {
            return 0;
        }

        $disk = PrivateStorage::disk();
        $count = 0;

        foreach (array_keys(SpeechFiles::index([WelcomeMessage::DIRECTORY, LevelMessage::DIRECTORY])) as $path) {
            $wav = (string) $disk->get($path);
            $normalized = Loudness::normalize($wav);

            if ($normalized !== $wav) {
                $disk->put($path, $normalized);
                $count++;
            }
        }

        Cache::forever($key, true);

        return $count;
    }

    public static function summary(array $result): string
    {
        $welcome = $result['welcome'];
        $levels = $result['levels'];
        $left = ($welcome['total'] - $welcome['ready']) + ($levels['total'] - $levels['ready']);
        $exhausted = implode(' et ', $result['exhausted'] ?? []) ?: 'le fournisseur';
        $lines = [
            "Messages de bienvenue : {$welcome['ready']} prêts sur {$welcome['total']}.",
            "Messages du résultat de l'évaluation : {$levels['ready']} prêts sur {$levels['total']}.",
        ];

        if ($left === 0) {
            $lines[] = 'Tout est prêt.';

            return implode(' ', $lines);
        }

        $lines[] = match ($result['stopped']) {
            'budget' => "Il en reste {$left} : le temps accordé est écoulé, relancez la préparation.",
            SpeechFailed::QUOTA => "Il en reste {$left} : {$exhausted} limite le nombre de demandes par minute (erreur 429). Relancez dans quelques minutes.",
            SpeechFailed::QUOTA_DAILY => "Il en reste {$left} : limite de demandes du jour atteinte ou crédit épuisé chez {$exhausted} (erreur 429). Relancez demain.",
            SpeechFailed::MISSING_KEY => "Il en reste {$left} : aucune clé de synthèse vocale n'est présente (".(implode(', ', $result['keys'] ?? []) ?: 'TTS_PROVIDERS vide').').',
            SpeechFailed::STORAGE => "Il en reste {$left} : l'écriture dans le stockage privé a échoué.",
            default => "Il en reste {$left}.".($result['error'] ? ' Dernière erreur : '.$result['error'] : ''),
        };

        return implode(' ', $lines);
    }
}
