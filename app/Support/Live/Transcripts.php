<?php

namespace App\Support\Live;

use App\Models\Assessment;
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
     * remplacée par sa version la plus longue : le navigateur renvoie une réplique pendant qu'elle grandit.
     * L'heure donnée par le navigateur est ramenée entre le début de la séance et maintenant.
     *
     * @param  list<array{seq: int, speaker: string, text: string, at?: int|null}>  $lines
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
                ->pluck('text', 'position');

            foreach ($lines as $line) {
                $text = trim(mb_substr((string) $line['text'], 0, $limit));
                $position = (int) $line['seq'];

                if ($text === '') {
                    continue;
                }

                if ($existing->has($position)) {
                    if (mb_strlen($text) > mb_strlen((string) $existing[$position])) {
                        ConversationLine::query()
                            ->where('conversation_type', $type)
                            ->where('conversation_id', $conversation->getKey())
                            ->where('position', $position)
                            ->update(['text' => $text, 'updated_at' => now()]);
                        $existing[$position] = $text;
                    }

                    $kept++;

                    continue;
                }

                // Plafond par séance : au-delà, rien de nouveau n'est gardé.
                if ($existing->count() >= $max) {
                    continue;
                }

                $at = isset($line['at']) ? Carbon::createFromTimestampMs((int) $line['at']) : now();

                ConversationLine::query()->create([
                    'user_id' => $conversation->user_id,
                    'conversation_type' => $type,
                    'conversation_id' => $conversation->getKey(),
                    'position' => $position,
                    'speaker' => $line['speaker'],
                    'text' => $text,
                    'spoken_at' => $at->max($start)->min(now()),
                ]);
                $existing[$position] = $text;
                $kept++;
            }
        });

        return $kept;
    }

    /**
     * @return Collection<int, ConversationLine>
     */
    public static function lines(Assessment|CourseSession|Lesson|LevelTest $conversation): Collection
    {
        return ConversationLine::query()
            ->where('conversation_type', self::typeOf($conversation))
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('position')
            ->get();
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
        Assessment::query()->whereNotNull('transcript')->where('started_at', '<', $limit)->update(['transcript' => null]);

        return $deleted;
    }
}
