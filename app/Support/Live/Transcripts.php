<?php

namespace App\Support\Live;

use App\Models\Assessment;
use App\Models\ConversationEvent;
use App\Models\ConversationLine;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Texte des conversations orales (jamais l'audio) : enregistrement des répliques envoyées par le navigateur,
 * lecture par l'administrateur, effacement après platform.transcripts.keep_days jours.
 */
final class Transcripts
{
    /** Types de séance (clé enregistrée en base => modèle). */
    public const TYPES = [
        'assessment' => Assessment::class,
        'lesson' => Lesson::class,
        'course' => CourseSession::class,
        'level_test' => LevelTest::class,
    ];

    /**
     * Mesures d'un tour gardées avec une réplique (jamais l'audio), en millisecondes :
     * apprenant : début et fin de la parole selon le micro (mic_*) et selon le fournisseur (provider_*), fin du
     * tour vue par le fournisseur (turn_end), fin de tour signalée par le navigateur (end_of_turn_sent) ;
     * professeur : délai de réponse (delay_ms), fin de la parole retenue (learner_end), arrivée du premier son (audio_at).
     */
    public const METRICS = [
        'mic_start', 'mic_end', 'provider_start', 'provider_end', 'turn_end', 'end_of_turn_sent',
        'delay_ms', 'learner_end', 'audio_at',
    ];

    public const LABELS = [
        'assessment' => 'Évaluation gratuite',
        'lesson' => 'Séance du jour',
        'course' => '« Mes cours »',
        'level_test' => 'Bilan de niveau',
    ];

    public static function typeOf(Model $conversation): string
    {
        return (string) array_search($conversation::class, self::TYPES, true);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }

    /**
     * La séance d'un type et d'un identifiant, seulement si elle appartient à cet utilisateur.
     */
    public static function find(string $type, int $id, User $user): Assessment|CourseSession|Lesson|LevelTest|null
    {
        $class = self::TYPES[$type] ?? null;

        return $class === null ? null : $class::query()->where('user_id', $user->id)->find($id);
    }

    /**
     * La séance accepte-t-elle encore des répliques ? Commencée il y a moins de accept_hours heures.
     */
    public static function accepts(Assessment|CourseSession|Lesson|LevelTest $conversation): bool
    {
        return $conversation->started_at !== null
            && $conversation->started_at->gt(now()->subHours((int) config('platform.transcripts.accept_hours')));
    }

    /**
     * Enregistre (ou complète) les répliques reçues. Une réplique déjà reçue sous le même numéro est
     * remplacée par sa version la plus longue : le navigateur renvoie une réplique pendant qu'elle grandit, ou
     * quand l'heure réelle de la parole et les mesures du tour sont connues (mises à jour, mesures fusionnées).
     * Les heures données par le navigateur sont ramenées entre le début de la séance et maintenant.
     *
     * @param  list<array{seq: int, speaker: string, text: string, at?: int|null, end?: int|null, metrics?: array<string, int|null>|null}>  $lines
     * @return int nombre de répliques gardées
     */
    public static function record(Assessment|CourseSession|Lesson|LevelTest $conversation, array $lines): int
    {
        $type = self::typeOf($conversation);
        $max = (int) config('platform.transcripts.max_lines_per_conversation');
        $limit = (int) config('platform.transcripts.max_line_characters');
        $start = $conversation->started_at ?? now();
        $kept = 0;

        DB::transaction(function () use ($conversation, $type, $lines, $max, $limit, $start, &$kept) {
            $existing = ConversationLine::query()
                ->where('conversation_type', $type)
                ->where('conversation_id', $conversation->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy('position');

            foreach ($lines as $line) {
                $text = trim(mb_substr((string) $line['text'], 0, $limit));
                $position = (int) $line['seq'];

                if ($text === '') {
                    continue;
                }

                $times = array_filter([
                    'spoken_at' => self::clamp($line['at'] ?? null, $start),
                    'ended_at' => self::clamp($line['end'] ?? null, $start),
                ]);
                $metrics = self::metrics($line['metrics'] ?? null);

                if ($existing->has($position)) {
                    $row = $existing[$position];
                    $changes = $times;

                    if (mb_strlen($text) > mb_strlen((string) $row->text)) {
                        $changes['text'] = $text;
                    }

                    if ($metrics !== []) {
                        $changes['metrics'] = array_merge($row->metrics ?? [], $metrics);
                    }

                    if ($changes !== []) {
                        $row->forceFill($changes)->save();
                    }

                    $kept++;

                    continue;
                }

                // Plafond par séance : au-delà, rien de nouveau n'est gardé.
                if ($existing->count() >= $max) {
                    continue;
                }

                $existing[$position] = ConversationLine::query()->create([
                    'user_id' => $conversation->user_id,
                    'conversation_type' => $type,
                    'conversation_id' => $conversation->getKey(),
                    'position' => $position,
                    'speaker' => $line['speaker'],
                    'text' => $text,
                    'spoken_at' => $times['spoken_at'] ?? now(),
                    'ended_at' => $times['ended_at'] ?? null,
                    'metrics' => $metrics === [] ? null : $metrics,
                ]);
                $kept++;
            }
        });

        return $kept;
    }

    /**
     * Enregistre les événements de la séance (reconnexion, erreur, micro fermé…), au plus
     * platform.transcripts.max_events_per_conversation par séance (au-delà : ignorés sans erreur).
     *
     * @param  list<array{kind: string, detail?: string|null, at?: int|null}>  $events
     * @return int nombre d'événements gardés
     */
    public static function recordEvents(Assessment|CourseSession|Lesson|LevelTest $conversation, array $events): int
    {
        if ($events === []) {
            return 0;
        }

        $type = self::typeOf($conversation);
        $room = (int) config('platform.transcripts.max_events_per_conversation') - ConversationEvent::query()
            ->where('conversation_type', $type)
            ->where('conversation_id', $conversation->getKey())
            ->count();
        $start = $conversation->started_at ?? now();
        $rows = [];

        foreach (array_slice($events, 0, max(0, $room)) as $event) {
            $rows[] = [
                'user_id' => $conversation->user_id,
                'conversation_type' => $type,
                'conversation_id' => $conversation->getKey(),
                'kind' => $event['kind'],
                'detail' => filled($event['detail'] ?? null) ? mb_substr((string) $event['detail'], 0, 200) : null,
                'happened_at' => self::clamp($event['at'] ?? null, $start) ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            ConversationEvent::query()->insert($rows);
        }

        return count($rows);
    }

    /**
     * Heure donnée par le navigateur (millisecondes), ramenée entre le début de la séance et maintenant.
     */
    private static function clamp(?int $milliseconds, Carbon $start): ?Carbon
    {
        if ($milliseconds === null) {
            return null;
        }

        return Carbon::createFromTimestampMs($milliseconds)->max($start)->min(now());
    }

    /**
     * Seulement les mesures connues, en nombres entiers positifs.
     *
     * @return array<string, int>
     */
    private static function metrics(?array $metrics): array
    {
        $kept = [];

        foreach ((array) $metrics as $key => $value) {
            if (in_array($key, self::METRICS, true) && is_numeric($value) && $value >= 0) {
                $kept[$key] = (int) $value;
            }
        }

        return $kept;
    }

    /**
     * Répliques dans l'ordre où elles ont été dites (heure réelle du début de la parole, puis numéro).
     *
     * @return Collection<int, ConversationLine>
     */
    public static function lines(Assessment|CourseSession|Lesson|LevelTest $conversation): Collection
    {
        return ConversationLine::query()
            ->where('conversation_type', self::typeOf($conversation))
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('spoken_at')
            ->orderBy('position')
            ->get();
    }

    /**
     * @return Collection<int, ConversationEvent>
     */
    public static function events(Assessment|CourseSession|Lesson|LevelTest $conversation): Collection
    {
        return ConversationEvent::query()
            ->where('conversation_type', self::typeOf($conversation))
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('happened_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Délais de réponse mesurés de la séance (millisecondes) : médian et le plus long.
     *
     * @param  Collection<int, ConversationLine>  $lines
     * @return array{count: int, median: int|null, max: int|null}
     */
    public static function delays(Collection $lines): array
    {
        $delays = $lines->map(fn (ConversationLine $line) => $line->delayMs())->filter(fn (?int $delay) => $delay !== null)->sort()->values();
        $count = $delays->count();

        if ($count === 0) {
            return ['count' => 0, 'median' => null, 'max' => null];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $delays[$middle] : intdiv($delays[$middle - 1] + $delays[$middle], 2);

        return ['count' => $count, 'median' => (int) $median, 'max' => (int) $delays->last()];
    }

    /**
     * « 1,4 seconde », « 12,3 secondes » (en toutes lettres, sans abréviation).
     */
    public static function seconds(int $milliseconds): string
    {
        $seconds = round($milliseconds / 1000, 1);

        return number_format($seconds, 1, ',', ' ').' '.($seconds < 2 ? 'seconde' : 'secondes');
    }

    /**
     * Les séances d'un apprenant, de la plus récente à la plus ancienne, avec le nombre de répliques gardées.
     *
     * @return Collection<int, array{type: string, label: string, conversation: Assessment|CourseSession|Lesson|LevelTest, lines: int}>
     */
    public static function sessionsOf(User $user, int $limit = 50): Collection
    {
        $counts = ConversationLine::query()
            ->where('user_id', $user->id)
            ->selectRaw('conversation_type, conversation_id, count(*) as aggregate')
            ->groupBy('conversation_type', 'conversation_id')
            ->get()
            ->mapWithKeys(fn (ConversationLine $row) => [$row->conversation_type.':'.$row->conversation_id => (int) $row->getAttribute('aggregate')]);

        $rows = collect();

        foreach (self::TYPES as $type => $class) {
            foreach ($class::query()->where('user_id', $user->id)->whereNotNull('started_at')->latest('started_at')->limit($limit)->get() as $conversation) {
                $rows->push([
                    'type' => $type,
                    'label' => self::label($type),
                    'conversation' => $conversation,
                    'lines' => $counts[$type.':'.$conversation->getKey()] ?? 0,
                ]);
            }
        }

        return $rows->sortByDesc(fn (array $row) => $row['conversation']->started_at)->take($limit)->values();
    }

    /**
     * Le verdict de la séance, en français, ou null s'il n'y en a pas.
     */
    public static function verdict(Assessment|CourseSession|Lesson|LevelTest $conversation): ?string
    {
        return match (true) {
            $conversation instanceof Assessment => $conversation->level !== null ? 'Niveau '.$conversation->level->value.' ('.$conversation->level->label().')' : null,
            $conversation instanceof Lesson => match ($conversation->outcome) {
                'completed' => 'Leçon terminée',
                'repeat' => 'Leçon à reprendre',
                default => null,
            },
            $conversation instanceof LevelTest => $conversation->passed === null ? null : ($conversation->passed ? 'Bilan réussi (niveau '.$conversation->level->value.')' : 'Bilan à repasser'),
            default => null,
        };
    }

    /**
     * Efface tout le texte des conversations d'un apprenant (demande de suppression), sous-titres de
     * l'évaluation compris. Renvoie le nombre de répliques effacées.
     */
    public static function forget(User $user): int
    {
        $deleted = ConversationLine::query()->where('user_id', $user->id)->delete();
        ConversationEvent::query()->where('user_id', $user->id)->delete();
        Assessment::query()->where('user_id', $user->id)->update(['transcript' => null]);

        return $deleted;
    }

    /**
     * Efface le texte des conversations plus ancien que keep_days jours, sous-titres de l'évaluation compris.
     */
    public static function purge(): int
    {
        $limit = now()->subDays((int) config('platform.transcripts.keep_days'));
        $deleted = ConversationLine::query()->where('created_at', '<', $limit)->delete();
        ConversationEvent::query()->where('created_at', '<', $limit)->delete();
        Assessment::query()->whereNotNull('transcript')->where('started_at', '<', $limit)->update(['transcript' => null]);

        return $deleted;
    }
}
