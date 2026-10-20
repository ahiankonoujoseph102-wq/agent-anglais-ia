<?php

namespace App\Support;

use App\Enums\LessonStatus;
use App\Models\CourseReview;
use App\Models\CourseSession;
use App\Support\Gemini\CourseReviewWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Documents personnels de révision des séances « Mes cours » (config/platform.php → courses.review) : une ligne
 * par séance finie, préparée hors de toute requête de l'élève par la tâche du planificateur
 * (« courses:prepare-reviews »), quelques séances par passage, pour ne jamais épuiser d'un coup le quota du modèle
 * texte. Un échec ne bloque rien : nouvel essai plus tard, puis « Réessayer ».
 */
class CourseReviews
{
    public const TOO_SHORT = 'too_short';

    /**
     * Crée la ligne des séances finies depuis assez longtemps (le texte de la séance a eu le temps d'arriver) ;
     * une séance trop courte reçoit une ligne « skipped » (la page explique pourquoi il n'y a pas de document).
     */
    public static function enqueue(int $limit = 20): int
    {
        $sessions = CourseSession::query()
            ->whereDoesntHave('review')
            ->whereNotNull('course_lesson_id')
            ->where('status', '!=', LessonStatus::InProgress)
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', now()->subSeconds((int) self::setting('delay_seconds')))
            ->where('ended_at', '>=', now()->subDays((int) self::setting('lookback_days')))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($sessions as $session) {
            $tooShort = $session->used_seconds < (int) self::setting('min_seconds')
                || CourseReviewWriter::learnerWords($session) < (int) self::setting('min_learner_words');

            CourseReview::query()->firstOrCreate(['course_session_id' => $session->id], [
                'user_id' => $session->user_id,
                'course_lesson_id' => $session->course_lesson_id,
                'status' => $tooShort ? CourseReview::SKIPPED : CourseReview::PENDING,
                'reason' => $tooShort ? self::TOO_SHORT : null,
            ]);
        }

        return $sessions->count();
    }

    /**
     * Documents à préparer maintenant (jamais avant l'heure du prochain essai), les plus anciens d'abord.
     *
     * @return Collection<int, CourseReview>
     */
    public static function due(int $limit): Collection
    {
        return CourseReview::query()
            ->where('status', CourseReview::PENDING)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Prépare un document. true s'il est prêt ; false si le modèle texte n'a pas répondu (nouvel essai plus tard,
     * ou échec définitif après max_attempts essais). Jamais d'exception vers l'appelant.
     */
    public static function prepare(CourseReview $review, ?CourseReviewWriter $writer = null): bool
    {
        $lock = Cache::lock('course-review:'.$review->id, 2 * (int) self::setting('timeout_seconds') + 30);

        if (! $lock->get()) {
            return false;
        }

        try {
            $review->refresh();

            if (! $review->isPending()) {
                return $review->isReady();
            }

            $session = $review->session()->with(['lesson', 'part'])->first();
            $review->attempts++;

            try {
                $written = $session?->lesson !== null
                    ? ($writer ?? new CourseReviewWriter)->write($session, (int) self::setting('timeout_seconds'))
                    : null;
            } catch (Throwable $e) {
                report($e);
                $written = null;
            }

            if ($written === null) {
                $failed = $review->attempts >= (int) self::setting('max_attempts');
                $review->status = $failed ? CourseReview::FAILED : CourseReview::PENDING;
                $review->next_attempt_at = $failed ? null : now()->addMinutes((int) self::setting('retry_minutes'));
                $review->save();

                return false;
            }

            $review->fill([
                'content' => $written['content'],
                'provider' => $written['provider'],
                'status' => CourseReview::READY,
                'prepared_at' => now(),
                'next_attempt_at' => null,
            ])->save();

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * « Réessayer » : un document en échec repart pour la prochaine tâche du planificateur.
     */
    public static function retry(CourseReview $review): void
    {
        if ($review->isFailed()) {
            $review->fill(['status' => CourseReview::PENDING, 'attempts' => 0, 'next_attempt_at' => null])->save();
        }
    }

    public static function setting(string $key): mixed
    {
        return config('platform.courses.review.'.$key);
    }
}
