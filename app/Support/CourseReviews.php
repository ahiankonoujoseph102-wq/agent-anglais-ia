<?php

namespace App\Support;

use App\Enums\LessonStatus;
use App\Models\CourseReview;
use App\Models\CourseSession;
use App\Support\Gemini\CourseReviewWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

            // Texte déjà rédigé lors d'un essai précédent (seul le PDF avait échoué) : aucune nouvelle demande.
            if ($review->content === null) {
                try {
                    $written = $session?->lesson !== null
                        ? ($writer ?? new CourseReviewWriter)->write($session, (int) self::setting('timeout_seconds'))
                        : null;
                } catch (Throwable $e) {
                    report($e);
                    $written = null;
                }

                if ($written === null) {
                    return self::failed($review);
                }

                $review->fill(['content' => $written['content'], 'provider' => $written['provider']]);
            }

            try {
                $path = trim((string) self::setting('directory'), '/').'/'.$review->user_id.'/'.$review->course_session_id.'-'.Str::random(16).'.pdf';
                PrivateStorage::disk()->put($path, CourseReviewPdf::render($review));
            } catch (Throwable $e) {
                report($e);

                return self::failed($review);
            }

            $review->fill([
                'path' => $path,
                'status' => CourseReview::READY,
                'prepared_at' => now(),
                'next_attempt_at' => null,
            ])->save();

            // Par e-mail (PDF joint) si l'élève a une adresse : jamais bloquant, le document reste téléchargeable.
            try {
                CourseReviewMailer::send($review);
            } catch (Throwable $e) {
                report($e);
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Essai raté : nouvel essai après retry_minutes, ou échec définitif après max_attempts essais.
     */
    private static function failed(CourseReview $review): bool
    {
        $failed = $review->attempts >= (int) self::setting('max_attempts');
        $review->status = $failed ? CourseReview::FAILED : CourseReview::PENDING;
        $review->next_attempt_at = $failed ? null : now()->addMinutes((int) self::setting('retry_minutes'));
        $review->save();

        return false;
    }

    /**
     * Contenu du PDF d'un document prêt (disque privé), ou null s'il manque.
     */
    public static function file(CourseReview $review): ?string
    {
        if (! $review->isReady() || $review->path === null || ! PrivateStorage::disk()->exists($review->path)) {
            return null;
        }

        return PrivateStorage::disk()->get($review->path);
    }

    /**
     * Nom du fichier téléchargé : « document-de-revision-le-present-simple-2026-10-20.pdf ».
     */
    public static function filename(CourseReview $review): string
    {
        return 'document-de-revision-'.Str::slug((string) ($review->lesson?->title ?? 'lecon')).'-'.($review->prepared_at ?? now())->timezone(config('app.timezone'))->format('Y-m-d').'.pdf';
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

    /**
     * Ce que l'élève voit pour une séance : ready, pending (en préparation, aussi avant la création de la ligne),
     * failed, skipped (séance trop courte) ou none (séance en cours, ou trop ancienne pour un document).
     */
    public static function stateOf(CourseSession $session): string
    {
        $review = $session->relationLoaded('review') ? $session->review : $session->review()->first();

        if ($review !== null) {
            return $review->status;
        }

        $recent = $session->ended_at !== null && $session->ended_at->gte(now()->subDays((int) self::setting('lookback_days')));

        return $session->status !== LessonStatus::InProgress && $recent && $session->course_lesson_id !== null ? CourseReview::PENDING : 'none';
    }

    public static function setting(string $key): mixed
    {
        return config('platform.courses.review.'.$key);
    }
}
