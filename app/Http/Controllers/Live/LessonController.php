<?php

namespace App\Http\Controllers\Live;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\LessonReportRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Duration;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La séance du jour des abonnés, limitée au temps restant de leur formule.
 */
class LessonController extends Controller
{
    private const NO_SUBSCRIPTION = 'Votre abonnement n\'est pas actif : choisissez une formule pour suivre vos séances.';

    private const NO_TIME_LEFT = 'Votre temps de séance est épuisé pour aujourd\'hui. Revenez demain !';

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->activeSubscription === null) {
            // Abonnement expiré ou jamais pris : on guide vers le (re)nouvellement.
            return to_route('payment.plans')->with('status', $user->latestSubscription !== null
                ? 'Votre abonnement a expiré : renouvelez-le pour reprendre vos séances.'
                : self::NO_SUBSCRIPTION);
        }

        $lesson = $this->openLesson($user);
        $remaining = $lesson?->remainingSeconds() ?? $user->remainingSecondsToday();

        if ($remaining < (int) config('platform.live.min_start_seconds')) {
            return to_route('dashboard')->with('status', self::NO_TIME_LEFT);
        }

        return view('live.session', [
            'mode' => 'lesson',
            'title' => 'Ma séance du jour',
            'intro' => 'Il vous reste '.Duration::format($remaining).' de séance aujourd\'hui. Le professeur vous annoncera la durée, puis vous préviendra deux minutes avant la fin.',
            'remainingSeconds' => $remaining,
            'urls' => [
                'connect' => route('lesson.connect'),
                'signal' => route('lesson.signal'),
                'finish' => route('lesson.finish'),
                'result' => route('lesson.report'),
                'done' => route('dashboard'),
            ],
        ]);
    }

    public function connect(ConnectRequest $request, LiveConnection $live): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if ($subscription === null) {
            return $this->refuse(self::NO_SUBSCRIPTION, 403);
        }

        $lesson = $this->openLesson($user);

        if ($lesson !== null && ! $lesson->canConnectAgain()) {
            return $this->refuse('Trop de reconnexions pour cette séance. Rechargez la page pour en commencer une nouvelle.', 429);
        }

        // Une séance ouverte est reprise ; sinon on en commence une avec le temps restant du jour.
        $allowedSeconds = $lesson?->allowed_seconds ?? (int) $user->remainingSecondsToday();

        if ($lesson === null && $allowedSeconds < (int) config('platform.live.min_start_seconds')) {
            return $this->refuse(self::NO_TIME_LEFT, 403);
        }

        $deadline = $lesson?->deadline() ?? now()->addSeconds($allowedSeconds);
        $setup = TeacherSetup::lesson($user, (int) ceil($allowedSeconds / 60), $request->handle());

        try {
            $token = $live->request($setup, $deadline);
        } catch (GeminiUnavailable $e) {
            report($e);

            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        $resumed = $lesson !== null;

        $lesson ??= $user->lessons()->create([
            'subscription_id' => $subscription->id,
            'date' => today(),
            'allowed_seconds' => $allowedSeconds,
            'started_at' => now(),
            'status' => LessonStatus::InProgress,
        ]);

        $warningMinutes = (int) config('platform.lessons.warning_minutes_before_end');

        return response()->json($live->attach($lesson, $token, $setup, [
            'start' => TeacherSetup::cue('lesson_start', ['minutes' => (int) ceil($allowedSeconds / 60)]),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('lesson_warning', ['remaining' => $warningMinutes]),
        ], $warningMinutes * 60, $resumed));
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $lesson = $this->latestLesson($request->user());

        $lesson->recordSignal($request->inputTokens(), $request->outputTokens());
        $active = $lesson->closeIfOver();

        return response()->json([
            'active' => $active,
            'remaining_seconds' => $lesson->remainingSeconds(),
        ]);
    }

    /**
     * Bilan et devoir transmis par le professeur (fonction « end_lesson »).
     */
    public function report(LessonReportRequest $request): JsonResponse
    {
        $lesson = $this->latestLesson($request->user());

        $lesson->update([
            'summary' => $request->validated('summary'),
            'homework' => $request->validated('homework'),
        ]);

        return response()->json(['saved' => true]);
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $lesson = $this->latestLesson($request->user());

        $lesson->recordSignal($request->inputTokens(), $request->outputTokens());

        if ($lesson->closeIfOver()) {
            $lesson->finish(now());
        }

        return response()->json(['status' => $lesson->status->label()]);
    }

    /**
     * La séance en cours, après avoir clôturé celles qui sont finies ou abandonnées.
     */
    private function openLesson(User $user): ?Lesson
    {
        $open = $user->lessons()
            ->where('status', LessonStatus::InProgress)
            ->latest('id')
            ->get()
            ->filter(fn (Lesson $lesson) => $lesson->closeIfOver());

        return $open->first();
    }

    /**
     * La dernière séance commencée aujourd'hui (celle que le navigateur suit).
     */
    private function latestLesson(User $user): Lesson
    {
        return $user->lessons()
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
