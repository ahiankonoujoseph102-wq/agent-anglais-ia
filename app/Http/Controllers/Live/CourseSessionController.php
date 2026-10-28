<?php

namespace App\Http\Controllers\Live;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\CourseLesson;
use App\Models\CourseSession;
use App\Models\User;
use App\Support\DailyTime;
use App\Support\Duration;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\LivePage;
use App\Support\Live\WorkingTogether;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Séance « Mes cours » : Teacher Joe aide l'apprenant sur une leçon qu'il a apportée, par la voix
 * (même technique que la séance orale du jour). Elles prennent sur le temps du jour de la formule, partagé
 * avec la séance orale (User::remainingSecondsToday) : une séance dure au plus courses.max_session_minutes
 * (10 minutes), jamais plus que le temps restant du jour, et n'est pas ouverte sous
 * daily_time.min_start_seconds (2 minutes). Le serveur accorde ce temps au début et coupe à la fin.
 */
class CourseSessionController extends Controller
{
    private const NO_ACCESS = 'Mes cours demande une formule active : choisissez votre formule pour l\'utiliser.';

    private const CHOOSE_PART = 'Choisissez d\'abord la partie de votre PDF à travailler (les pages ou le passage).';

    public function show(Request $request, CourseLesson $course): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($course->user_id === $user->id, 404);

        $open = $this->openSession($user);

        if ($open === null && $user->activeSubscription === null) {
            return to_route('courses.index');
        }

        if ($open === null && ! $user->canStartSessionToday()) {
            return to_route('courses.show', $course)->with('status', DailyTime::EXHAUSTED);
        }

        // Long PDF : la partie à travailler est choisie (et lue) avant la séance.
        if ($open === null && ! $course->readyForSession()) {
            return redirect()->to(route('courses.show', $course).'#partie')->with('status', self::CHOOSE_PART);
        }

        $remaining = $open?->remainingSeconds() ?? self::allowedSeconds($user);

        return view('live.session', [
            'mode' => 'course',
            'usefulPhrasesOpen' => WorkingTogether::isFirst(WorkingTogether::courseNumber($user, $open)),
            'title' => 'Mon cours',
            // UNE ligne courte sous « Commencer » ; le détail est replié sous « Avant de commencer ».
            'intro' => config('avatar.model.name').' a lu votre leçon « '.$course->title.' »'.($course->currentPart ? ' ('.$course->currentPart->label().')' : '').'.',
            'introMore' => 'Il vous demandera ce que vous voulez faire : comprendre toute la leçon, poser une question précise ou travailler un exercice. '
                .'Cette séance dure '.Duration::format($remaining).' au plus ; il vous préviendra avant la fin. '
                .'Elle prend sur votre temps du jour, partagé avec la séance orale. '.DailyTime::remainingSentence((int) $user->remainingSecondsToday(false)),
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

        // Deux demandes en même temps (même avec la séance orale) ne peuvent pas dépasser le temps du jour.
        $lock = Cache::lock(LessonController::connectLockKey($user), 20);

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

        if ($refusal = LivePage::refusal($session)) {
            return $refusal;
        }

        $session->recordSignal($request->inputTokens(), $request->outputTokens(), $request->connected(), $request->tokenDetails());
        $active = $session->closeIfOver();

        return response()->json([
            'active' => $active,
            'remaining_seconds' => $session->remainingSeconds(),
        ]);
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $session = $this->latestSession($request->user());

        if ($refusal = LivePage::refusal($session)) {
            return $refusal;
        }

        $session->recordSignal($request->inputTokens(), $request->outputTokens(), null, $request->tokenDetails());

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

            if (! $user->canStartSessionToday()) {
                return $this->refuse(DailyTime::EXHAUSTED, 403);
            }

            if (! $course->readyForSession()) {
                return $this->refuse(self::CHOOSE_PART, 409);
            }
        } elseif (! $session->canConnectAgain()) {
            return $this->refuse('Trop de reconnexions pour cette séance. Rechargez la page pour en commencer une nouvelle.', 429);
        }

        $allowedSeconds = $session?->allowed_seconds ?? self::allowedSeconds($user);
        $deadline = $session?->deadline() ?? now()->addSeconds($allowedSeconds);
        $setup = TeacherSetup::course($user, (int) ceil($allowedSeconds / 60), $course->promptContext(), $request->handle(),
            WorkingTogether::courseNumber($user, $session));

        try {
            $token = $live->request($setup, $deadline, $session, $request->failedProvider());
        } catch (GeminiUnavailable $e) {
            report($e);

            // Pas de séance créée : un jeton refusé ne prend rien sur le temps du jour.
            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        $resumed = $session !== null;

        $session ??= $user->courseSessions()->create([
            'course_lesson_id' => $course->id,
            // Partie du PDF travaillée : le document de révision portera sur elle.
            'course_part_id' => $course->current_part_id,
            'date' => today(),
            'allowed_seconds' => $allowedSeconds,
            'started_at' => now(),
            'status' => LessonStatus::InProgress,
        ]);

        // Séance raccourcie au temps restant du jour : le préavis ne tombe jamais avant la moitié de la séance.
        $warning = min((int) config('platform.courses.warning_seconds'), intdiv((int) $session->allowed_seconds, 2));

        return response()->json($live->attach($session, $token, $setup, [
            'start' => TeacherSetup::cue('course_start', ['minutes' => (int) ceil($allowedSeconds / 60)]),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('course_warning', ['remaining' => $warning]),
        ], $warning, $resumed));
    }

    /**
     * Temps accordé à une nouvelle séance : la durée maximale d'une séance « Mes cours », jamais plus
     * que le temps restant du jour.
     */
    private static function allowedSeconds(User $user): int
    {
        return min((int) config('platform.courses.max_session_minutes') * 60, (int) $user->remainingSecondsToday());
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
