<?php

namespace App\Http\Controllers\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\LessonReportRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\Lesson;
use App\Models\ProgramLesson;
use App\Models\User;
use App\Support\Curriculum\LessonContext;
use App\Support\Curriculum\LessonReport;
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
        // Une séance commencée va jusqu'à sa fin, même si l'accès (provisoire) s'arrête entre-temps.
        $lesson = $this->openLesson($user);

        if ($user->activeSubscription === null && $lesson === null) {
            return $this->toPayment($user);
        }

        if ($gate = $this->programGate($user)) {
            return redirect($gate['url'])->with('status', $gate['message']);
        }
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
        // Renouvellement du jeton d'une séance commencée : jamais refusé pour l'abonnement,
        // la séance n'est pas coupée brutalement. Aucune NOUVELLE séance sans accès.
        $lesson = $this->openLesson($user);

        if ($subscription === null && $lesson === null) {
            return $this->refuse(self::NO_SUBSCRIPTION, 403);
        }

        if ($gate = $this->programGate($user)) {
            return $this->refuse($gate['message'], 409);
        }

        if ($lesson !== null && ! $lesson->canConnectAgain()) {
            return $this->refuse('Trop de reconnexions pour cette séance. Rechargez la page pour en commencer une nouvelle.', 429);
        }

        // Une séance ouverte est reprise ; sinon on en commence une avec le temps restant du jour.
        $allowedSeconds = $lesson?->allowed_seconds ?? (int) $user->remainingSecondsToday();

        if ($lesson === null && $allowedSeconds < (int) config('platform.live.min_start_seconds')) {
            return $this->refuse(self::NO_TIME_LEFT, 403);
        }

        $deadline = $lesson?->deadline() ?? now()->addSeconds($allowedSeconds);
        // La leçon du programme : celle de la séance reprise, sinon la prochaine à faire.
        $program = $user->activeProgram()->first();
        $programLesson = $lesson?->programLesson ?? $program?->currentLesson()->first();
        $programLesson?->setRelation('program', $program);

        $setup = TeacherSetup::lesson($user, (int) ceil($allowedSeconds / 60), $request->handle(), $this->context($user, $programLesson));

        try {
            $token = $live->request($setup, $deadline, $lesson, $request->failedProvider());
        } catch (GeminiUnavailable $e) {
            report($e);

            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        $resumed = $lesson !== null;

        $lesson ??= $user->lessons()->create([
            'subscription_id' => $subscription->id,
            'program_id' => $program?->id,
            'program_lesson_id' => $programLesson?->id,
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

        // Un seul bilan par séance, envoyé pendant la séance ou juste après sa fin.
        if ($lesson->reported_at !== null || ($lesson->ended_at !== null && $lesson->ended_at->lt(now()->subMinutes(5)))) {
            return $this->refuse('Le bilan de cette séance est déjà enregistré.', 409);
        }

        LessonReport::record($lesson, $request->validated());

        return response()->json([
            'saved' => true,
            'outcome' => $lesson->outcome,
            'program_status' => $lesson->programLesson?->program->status->value,
        ]);
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
     * Avant les séances : un programme personnel, et pas de bilan de niveau en attente.
     * Après le programme C2 validé, les séances restent possibles en conversation libre.
     *
     * @return array{url: string, message: string}|null
     */
    private function programGate(User $user): ?array
    {
        $program = $user->activeProgram()->first();

        if ($program === null) {
            if ($user->attestations()->where('level', Level::C2)->exists()) {
                return null;
            }

            return ['url' => route('program.questionnaire', 1), 'message' => 'Avant votre première séance, construisons ensemble votre programme personnel.'];
        }

        if ($program->status === ProgramStatus::LevelTestDue) {
            return ['url' => route('level-test.show'), 'message' => 'Vous avez terminé les 12 unités : passez d\'abord votre bilan de fin de niveau.'];
        }

        return null;
    }

    /**
     * Consignes de la séance : profil, leçon du jour, séance précédente, erreurs à revoir.
     */
    private function context(User $user, ?ProgramLesson $programLesson): ?string
    {
        if ($programLesson === null) {
            return null;
        }

        $previous = $user->lessons()->whereNotNull('reported_at')->latest('id')->first();
        $errors = $user->openErrors()->limit((int) config('program.errors_per_lesson'))->get();

        return LessonContext::describe($user, $programLesson->program, $programLesson, $previous, $errors);
    }

    /**
     * Pas d'accès : vers les formules, avec le message qui correspond à la situation.
     */
    private function toPayment(User $user): RedirectResponse
    {
        $payment = $user->pendingPayment()->first();

        if ($payment !== null) {
            // Accès provisoire terminé (ou jamais accordé) : la vérification est en cours.
            return to_route('payment.show', $payment);
        }

        return to_route('payment.plans')->with('status', $user->latestSubscription !== null
            ? 'Votre abonnement a expiré : renouvelez-le pour reprendre vos séances.'
            : self::NO_SUBSCRIPTION);
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
