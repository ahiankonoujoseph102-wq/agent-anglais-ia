<?php

namespace App\Http\Controllers\Live;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\CourseLesson;
use App\Models\CourseSession;
use App\Models\User;
use App\Support\Duration;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Séance « Mes cours » : Teacher Joe aide l'apprenant sur une leçon qu'il a apportée, par la voix
 * (même technique que la séance orale du jour). Limites réglables dans config/platform.php (courses) :
 * 5 séances par jour et par apprenant, 10 minutes au plus chacune, coupées par le serveur.
 * Ces séances ne consomment PAS les minutes de la séance orale quotidienne : compteur à part.
 */
class CourseSessionController extends Controller
{
    private const NO_ACCESS = 'Mes cours demande une formule active : choisissez votre formule pour l\'utiliser.';

    public function show(Request $request, CourseLesson $course): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($course->user_id === $user->id, 404);

        $open = $this->openSession($user);

        if ($open === null && $user->activeSubscription === null) {
            return to_route('courses.index');
        }

        if ($open === null && $this->limitReached($user)) {
            return to_route('courses.show', $course)->with('status', $this->limitMessage());
        }

        $minutes = (int) config('platform.courses.session_minutes');
        $remaining = $open?->remainingSeconds() ?? $minutes * 60;
        $used = $user->courseSessionsToday();
        $limit = (int) config('platform.courses.sessions_per_day');

        return view('live.session', [
            'mode' => 'course',
            'title' => 'Mon cours',
            'intro' => 'Teacher Joe a lu votre leçon « '.$course->title.' ». Il vous demandera ce que vous voulez faire : comprendre toute la leçon, poser une question précise ou travailler un exercice. '
                .'La séance dure '.Duration::format($minutes * 60).' au plus ; il vous préviendra avant la fin. '
                .'Cette séance est un complément : elle ne prend rien sur votre séance orale du jour. Leçons aujourd\'hui : '.$used.' sur '.$limit.' ; cette séance compte pour une leçon.',
            'remainingSeconds' => $remaining,
            'urls' => [
                'connect' => route('courses.connect', $course),
                'signal' => route('courses.signal'),
                'finish' => route('courses.finish'),
                'result' => route('courses.finish'),
                'done' => route('courses.show', $course),
            ],
        ]);
    }

    public function connect(ConnectRequest $request, LiveConnection $live, CourseLesson $course): JsonResponse
    {
        $user = $request->user();
        abort_unless($course->user_id === $user->id, 404);

        // Deux demandes en même temps ne peuvent pas dépasser la limite du jour.
        $lock = Cache::lock('course-connect:'.$user->id, 20);

        if (! $lock->get()) {
            return $this->refuse('Une connexion est déjà en cours. Patientez un instant, puis réessayez.', 429);
        }

        try {
            return $this->open($request, $live, $user, $course);
        } finally {
            $lock->release();
        }
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $session = $this->latestSession($request->user());

        $session->recordSignal($request->inputTokens(), $request->outputTokens());
        $active = $session->closeIfOver();

        return response()->json([
            'active' => $active,
            'remaining_seconds' => $session->remainingSeconds(),
        ]);
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $session = $this->latestSession($request->user());

        $session->recordSignal($request->inputTokens(), $request->outputTokens());

        if ($session->closeIfOver()) {
            $session->finish(now());
        }

        return response()->json(['status' => $session->status->label()]);
    }

    private function open(ConnectRequest $request, LiveConnection $live, User $user, CourseLesson $course): JsonResponse
    {
        // Séance commencée : reprise (renouvellement du jeton), même si l'accès s'arrête entre-temps.
        $session = $this->openSession($user);

        if ($session !== null && $session->course_lesson_id !== $course->id) {
            return $this->refuse('Une autre séance « Mes cours » est ouverte. Terminez-la d\'abord.', 409);
        }

        if ($session === null) {
            if ($user->activeSubscription === null) {
                return $this->refuse(self::NO_ACCESS, 403);
            }

            if ($this->limitReached($user)) {
                return $this->refuse($this->limitMessage(), 403);
            }
        } elseif (! $session->canConnectAgain()) {
            return $this->refuse('Trop de reconnexions pour cette séance. Rechargez la page pour en commencer une nouvelle.', 429);
        }

        $minutes = (int) config('platform.courses.session_minutes');
        $allowedSeconds = $session?->allowed_seconds ?? $minutes * 60;
        $deadline = $session?->deadline() ?? now()->addSeconds($allowedSeconds);
        $setup = TeacherSetup::course($user, (int) ceil($allowedSeconds / 60), $course->promptContext(), $request->handle());

        try {
            $token = $live->request($setup, $deadline);
        } catch (GeminiUnavailable $e) {
            report($e);

            // Pas de séance créée : un jeton refusé ne compte pas dans la limite du jour.
            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        $resumed = $session !== null;

        $session ??= $user->courseSessions()->create([
            'course_lesson_id' => $course->id,
            'date' => today(),
            'allowed_seconds' => $allowedSeconds,
            'started_at' => now(),
            'status' => LessonStatus::InProgress,
        ]);

        $warning = (int) config('platform.courses.warning_seconds');

        return response()->json($live->attach($session, $token, $setup, [
            'start' => TeacherSetup::cue('course_start', ['minutes' => (int) ceil($allowedSeconds / 60)]),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('course_warning', ['remaining' => $warning]),
        ], $warning, $resumed));
    }

    private function limitReached(User $user): bool
    {
        return $user->courseSessionsToday() >= (int) config('platform.courses.sessions_per_day');
    }

    private function limitMessage(): string
    {
        return 'Vous avez fait vos '.(int) config('platform.courses.sessions_per_day').' leçons « Mes cours » du jour. Revenez demain : votre séance orale du jour reste disponible.';
    }

    /**
     * La séance en cours, après avoir clôturé celles qui sont finies ou abandonnées.
     */
    private function openSession(User $user): ?CourseSession
    {
        return $user->courseSessions()
            ->where('status', LessonStatus::InProgress)
            ->latest('id')
            ->get()
            ->filter(fn (CourseSession $session) => $session->closeIfOver())
            ->first();
    }

    /**
     * La dernière séance commencée (celle que le navigateur suit).
     */
    private function latestSession(User $user): CourseSession
    {
        return $user->courseSessions()
            ->whereNotNull('started_at')
            ->where('started_at', '>=', now()->subDay())
            ->latest('id')
            ->firstOrFail();
    }

    private function refuse(string $message, int $status, bool $quota = false): JsonResponse
    {
        return response()->json(['message' => $message, 'quota' => $quota], $status);
    }
}
