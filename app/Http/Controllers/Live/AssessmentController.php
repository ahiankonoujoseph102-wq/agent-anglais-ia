<?php

namespace App\Http\Controllers\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Http\Controllers\Controller;
use App\Http\Requests\Live\AssessmentResultRequest;
use App\Http\Requests\Live\ChooseLevelRequest;
use App\Http\Requests\Live\ConnectRequest;
use App\Http\Requests\Live\SignalRequest;
use App\Models\Assessment;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use App\Support\Plan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * L'évaluation orale gratuite : une seule par compte, durée fixée par la configuration.
 */
class AssessmentController extends Controller
{
    private const ALREADY_DONE = 'Vous avez déjà passé votre évaluation gratuite : il n\'y en a qu\'une par compte.';

    public function show(Request $request): View|RedirectResponse
    {
        $assessment = $request->user()->assessment()->first();

        // Évaluation déjà passée : la page de résultat (niveau, message de Teacher Joe, formules).
        if ($assessment !== null && ! $assessment->closeIfOver()) {
            return $this->resultPage($request, $assessment);
        }

        $minutes = (int) config('platform.assessment.max_minutes');

        return view('live.session', [
            'mode' => 'assessment',
            'title' => 'Évaluation gratuite',
            'intro' => "Parlez avec votre professeur pendant {$minutes} minutes au maximum. Il vous posera des questions de plus en plus difficiles, puis vous annoncera votre niveau.",
            'remainingSeconds' => $assessment?->remainingSeconds() ?? $minutes * 60,
            'urls' => [
                'connect' => route('assessment.connect'),
                'signal' => route('assessment.signal'),
                'finish' => route('assessment.finish'),
                'result' => route('assessment.result'),
                // Fin de l'évaluation : la page de résultat complète (message de Teacher Joe, niveau, formules).
                'done' => route('assessment.show'),
            ],
        ]);
    }

    /**
     * Message vocal de Teacher Joe pour le niveau trouvé : généré une fois par niveau,
     * demandé seulement quand l'apprenant touche « Écouter ».
     */
    public function voice(Request $request, LevelMessage $message): Response
    {
        $level = $request->user()->assessment()->first()?->level;

        abort_if($level === null, 404);

        $wav = $message->get($level);

        abort_if($wav === null, 503, 'Le message vocal n\'est pas disponible pour le moment.');

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Length' => (string) strlen($wav),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * « Choisir un autre niveau » : le niveau choisi est celui du programme, tant qu'il n'est pas construit.
     */
    public function chooseLevel(ChooseLevelRequest $request): RedirectResponse
    {
        $user = $request->user();
        $assessment = $user->assessment()->first();

        abort_if($assessment === null || $assessment->isInProgress(), 404);

        if ($user->programs()->exists()) {
            return to_route('assessment.show')->with('status', 'Votre programme est déjà construit : son niveau ne change plus ici.');
        }

        $level = $request->level();
        $user->forceFill(['level' => $level])->save();

        $message = "Niveau choisi : {$level->value} ({$level->label()}). Votre programme sera construit à ce niveau.";

        if ($assessment->level !== null && self::rank($level) > self::rank($assessment->level)) {
            $message .= ' Ce niveau est plus élevé que celui de votre évaluation : vous pourrez revenir au niveau recommandé tant que votre programme n\'est pas construit.';
        }

        // Étape suivante : les formules, au niveau choisi.
        return redirect()->to(route('assessment.show').'#formules')->with('status', $message);
    }

    public function connect(ConnectRequest $request, LiveConnection $live): JsonResponse
    {
        $user = $request->user();
        $assessment = $user->assessment()->first();
        $resumed = $assessment !== null;

        if ($assessment !== null) {
            if (! $assessment->closeIfOver()) {
                return $this->refuse(self::ALREADY_DONE, 409);
            }

            if (! $assessment->canConnectAgain()) {
                return $this->refuse('Trop de reconnexions pour cette évaluation. Contactez-nous si le problème persiste.', 429);
            }
        }

        $allowedSeconds = $assessment?->allowed_seconds ?? (int) config('platform.assessment.max_minutes') * 60;
        $deadline = $assessment?->deadline() ?? now()->addSeconds($allowedSeconds);
        // Durée de l'évaluation elle-même (le moment des questions s'ajoute après le verdict).
        $setup = TeacherSetup::assessment($user, (int) config('platform.assessment.max_minutes'), $request->handle());

        try {
            $token = $live->request($setup, $deadline);
        } catch (GeminiUnavailable $e) {
            report($e);

            return $this->refuse($e->userMessage(), 503, quota: $e->quotaExceeded);
        }

        if ($assessment === null) {
            try {
                $assessment = $user->assessment()->create([
                    'status' => AssessmentStatus::InProgress,
                    'started_at' => now(),
                    'allowed_seconds' => $allowedSeconds,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Deux onglets en même temps : la base garantit l'unicité.
                return $this->refuse(self::ALREADY_DONE, 409);
            }
        }

        $warningMinutes = (int) config('platform.assessment.warning_minutes_before_end');

        return response()->json($live->attach($assessment, $token, $setup, [
            'start' => TeacherSetup::cue('assessment_start'),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('assessment_warning', ['remaining' => $warningMinutes]),
            'conclusion' => TeacherSetup::cue('assessment_conclusion', ['seconds' => (int) config('platform.assessment.conclusion_cue_seconds')]),
        ], $warningMinutes * 60, $resumed) + self::ending($assessment));
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $assessment = $this->current($request);

        $assessment->appendTranscript($request->transcript());
        $assessment->recordSignal($request->inputTokens(), $request->outputTokens());
        $active = $assessment->closeIfOver();

        return response()->json([
            'active' => $active,
            'remaining_seconds' => $assessment->remainingSeconds(),
        ]);
    }

    /**
     * Résultat transmis par le professeur (fonction « record_assessment_result »).
     */
    public function result(AssessmentResultRequest $request): JsonResponse
    {
        $assessment = $this->current($request);

        // Un seul résultat, pendant l'évaluation ou juste après sa coupure.
        $tooLate = ! $assessment->isInProgress()
            && ($assessment->ended_at === null || $assessment->ended_at->lt(now()->subMinutes(2)));

        if ($assessment->level !== null || $tooLate) {
            return $this->refuse('Le résultat de cette évaluation est déjà enregistré.', 409);
        }

        $assessment->recordResult($request->level(), $request->feedback());

        if (! $assessment->isInProgress()) {
            $assessment->update(['status' => AssessmentStatus::Completed]);
        }

        // Moment des questions libres et conclusion (seulement si la conversation est encore ouverte).
        $assessment->startQuestions();

        return response()->json([
            'level' => $assessment->level->value,
            'label' => $assessment->level->label(),
            'remaining_seconds' => $assessment->remainingSeconds(),
        ] + self::ending($assessment));
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $assessment = $this->current($request);

        $assessment->appendTranscript($request->transcript());
        $assessment->recordSignal($request->inputTokens(), $request->outputTokens());

        if ($assessment->closeIfOver()) {
            $assessment->finish(now());
        }

        // Niveau (annoncé par le professeur, ou estimé si le temps a fini avant son verdict).
        return response()->json([
            'status' => $assessment->status->label(),
            'level' => $assessment->level?->value,
            'label' => $assessment->level?->label(),
            'feedback' => $assessment->feedback,
        ]);
    }

    /**
     * Réglages de la fin de l'évaluation pour le navigateur : moment des questions en cours,
     * indication unique de conclusion, phrase fixe qui termine la conversation.
     *
     * @return array<string, mixed>
     */
    private static function ending(Assessment $assessment): array
    {
        return [
            'questions' => $assessment->inQuestions(),
            'conclusion_cue_seconds' => (int) config('platform.assessment.conclusion_cue_seconds'),
            'closing_phrase' => (string) config('platform.assessment.closing_phrase'),
            'closing_grace_seconds' => (int) config('platform.assessment.closing_grace_seconds'),
        ];
    }

    private function resultPage(Request $request, Assessment $assessment): View
    {
        $user = $request->user();

        return view('assessment.result', [
            'assessment' => $assessment,
            'found' => $assessment->level,
            'chosen' => $user->level,
            'levels' => Level::cases(),
            'rank' => array_flip(array_map(fn (Level $level) => $level->value, Level::cases())),
            'canChoose' => ! $user->programs()->exists(),
            'plans' => Plan::all(),
            'hasSubscription' => $user->activeSubscription()->exists(),
            'messageText' => $assessment->level ? LevelMessage::text($assessment->level) : null,
        ]);
    }

    private static function rank(Level $level): int
    {
        return array_search($level, Level::cases(), true);
    }

    private function current(Request $request): Assessment
    {
        $assessment = $request->user()->assessment()->first();

        abort_if($assessment === null, 404);

        return $assessment;
    }

    private function refuse(string $message, int $status, bool $quota = false): JsonResponse
    {
        return response()->json(['message' => $message, 'quota' => $quota], $status);
    }
}
