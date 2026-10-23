<?php

namespace App\Http\Controllers\Live;

use App\Enums\LevelTestStatus;
use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\LevelTestResultRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\LevelTest;
use App\Models\Program;
use App\Models\User;
use App\Support\Curriculum\LevelProgression;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bilan oral de fin de niveau, quand les 12 unités du programme sont terminées.
 */
class LevelTestController extends Controller
{
    private const NOT_DUE = 'Le bilan de fin de niveau s\'ouvre quand les 12 unités de votre programme sont terminées.';

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $program = $this->dueProgram($user);

        if ($program === null) {
            return to_route('dashboard')->with('status', $user->activeSubscription === null
                ? 'Votre abonnement n\'est pas actif : renouvelez-le pour passer votre bilan.'
                : self::NOT_DUE);
        }

        $test = $this->openTest($user);
        $minutes = (int) config('program.level_test_minutes');

        return view('live.session', [
            'mode' => 'level_test',
            'title' => 'Bilan de fin de niveau '.$program->level->value,
            // UNE ligne courte sous « Commencer » ; le détail est replié sous « Avant de commencer ».
            'intro' => "{$minutes} minutes au plus pour vérifier votre niveau {$program->level->value}.",
            'introMore' => "Le professeur vérifie que vous maîtrisez le niveau {$program->level->value}. "
                .'Si vous réussissez, vous passez au niveau suivant et recevez votre attestation de niveau.',
            'remainingSeconds' => $test?->remainingSeconds() ?? $minutes * 60,
            'urls' => [
                'connect' => route('level-test.connect'),
                'signal' => route('level-test.signal'),
                'finish' => route('level-test.finish'),
                'result' => route('level-test.result'),
                'done' => route('dashboard'),
            ],
        ]);
    }

    public function connect(ConnectRequest $request, LiveConnection $live): JsonResponse
    {
        $user = $request->user();
        $program = $this->dueProgram($user);

        if ($program === null) {
            return $this->refuse(self::NOT_DUE, 409);
        }

        $test = $this->openTest($user);

        if ($test !== null && ! $test->canConnectAgain()) {
            return $this->refuse('Trop de reconnexions pour ce bilan. Rechargez la page pour le recommencer.', 429);
        }

        $allowedSeconds = $test?->allowed_seconds ?? (int) config('program.level_test_minutes') * 60;
        $deadline = $test?->deadline() ?? now()->addSeconds($allowedSeconds);
        $setup = TeacherSetup::levelTest($user, $program->level, (int) ceil($allowedSeconds / 60), $request->handle());

        try {
            $token = $live->request($setup, $deadline, $test, $request->failedProvider());
        } catch (GeminiUnavailable $e) {
            report($e);

            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        $resumed = $test !== null;

        $test ??= $user->levelTests()->create([
            'program_id' => $program->id,
            'level' => $program->level,
            'status' => LevelTestStatus::InProgress,
            'started_at' => now(),
            'allowed_seconds' => $allowedSeconds,
        ]);

        $warningMinutes = 1;

        return response()->json($live->attach($test, $token, $setup, [
            'start' => TeacherSetup::cue('level_test_start'),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('level_test_warning', ['remaining' => $warningMinutes]),
        ], $warningMinutes * 60, $resumed));
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $test = $this->latestTest($request->user());

        $test->recordSignal($request->inputTokens(), $request->outputTokens(), $request->connected());
        $active = $test->closeIfOver();

        return response()->json([
            'active' => $active,
            'remaining_seconds' => $test->remainingSeconds(),
        ]);
    }

    /**
     * Résultat transmis par le professeur : réussite (niveau suivant, attestation) ou unités à reprendre.
     */
    public function result(LevelTestResultRequest $request, LevelProgression $progression): JsonResponse
    {
        $test = $this->latestTest($request->user());

        $tooLate = ! $test->isInProgress()
            && ($test->ended_at === null || $test->ended_at->lt(now()->subMinutes(2)));

        if ($test->hasResult() || $tooLate) {
            return $this->refuse('Le résultat de ce bilan est déjà enregistré.', 409);
        }

        // Un résultat n'est accepté qu'après un vrai temps d'échange avec le professeur.
        if ($test->elapsedUntil(now()) < (int) config('program.level_test_min_seconds')) {
            return $this->refuse('Le bilan est trop court pour être évalué.', 409);
        }

        $attestation = $progression->record(
            $test,
            $request->boolean('passed'),
            trim($request->validated('feedback')),
            $request->validated('units_to_review') ?? [],
        );

        if (! $test->isInProgress()) {
            $test->finish($test->ended_at);
        }

        return response()->json([
            'passed' => $test->passed,
            'level' => $test->level->value,
            'next_level' => $test->passed ? $test->level->next()?->value : null,
            'units_to_review' => $test->units_to_review,
            'attestation_url' => $attestation ? route('attestation.download', $attestation) : null,
        ]);
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $test = $this->latestTest($request->user());

        $test->recordSignal($request->inputTokens(), $request->outputTokens());

        if ($test->closeIfOver()) {
            $test->finish(now());
        }

        return response()->json(['status' => $test->status->label()]);
    }

    /**
     * Le programme dont le bilan est à passer (abonnement actif requis).
     */
    private function dueProgram(User $user): ?Program
    {
        $program = $user->activeProgram;

        if ($user->activeSubscription === null || $program?->status !== ProgramStatus::LevelTestDue) {
            return null;
        }

        return $program;
    }

    private function openTest(User $user): ?LevelTest
    {
        $test = $user->levelTests()->where('status', LevelTestStatus::InProgress)->latest('id')->first();

        return $test !== null && $test->closeIfOver() ? $test : null;
    }

    private function latestTest(User $user): LevelTest
    {
        return $user->levelTests()->latest('id')->firstOrFail();
    }

    private function refuse(string $message, int $status, bool $quota = false): JsonResponse
    {
        return response()->json(['message' => $message, 'quota' => $quota], $status);
    }
}
