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
use App\Support\Curriculum\LessonBoards;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\AssessmentMiniLesson;
use App\Support\Live\AssessmentVersion;
use App\Support\Live\LiveEarlyEnd;
use App\Support\Live\LivePage;
use App\Support\Plan;
use App\Support\Teachers\Teachers;
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
            // Premier contact avec Teacher Joe : phrases utiles dépliées.
            'usefulPhrasesOpen' => true,
            'title' => 'Évaluation gratuite',
            // UNE ligne courte sous « Commencer » ; le reste (ce qui suit, calme, droit à l'erreur, micro) est replié
            // sous « Avant de commencer » (live/before-start).
            'intro' => $minutes.' minutes à l\'oral avec '.config('avatar.model.name').', pour trouver votre niveau.',
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
     * État du message vocal du résultat : prêt ou non. Pas encore prêt : il se prépare après la réponse, la page
     * redemande et le joue dès qu'il est prêt.
     */
    public function voiceStatus(Request $request): JsonResponse
    {
        $level = $request->user()->assessment()->first()?->level;

        abort_if($level === null, 404);

        $ready = LevelMessage::ready($level);

        if (! $ready) {
            LevelMessage::queue($level);
        }

        return response()->json(['ready' => $ready]);
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
        // Évaluation gratuite : toujours le professeur par défaut (Teacher Joe), quel que soit le choix de l'apprenant.
        $setup = TeacherSetup::assessment($user, (int) config('platform.assessment.max_minutes'), $request->handle(), Teachers::default());

        try {
            // Avant le verdict, le jeton couvre aussi la mini-leçon et les questions : aucun renouvellement en route.
            $token = $live->request($setup, $deadline, $assessment, $request->failedProvider(),
                $assessment?->level === null ? Assessment::afterVerdictSeconds() : 0);
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
            'start' => TeacherSetup::cue(AssessmentVersion::isNewFor($user) ? 'assessment_start_new' : 'assessment_start'),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('assessment_warning', ['remaining' => $warningMinutes]),
            'conclusion' => TeacherSetup::cue('assessment_conclusion', ['seconds' => (int) config('platform.assessment.conclusion_cue_seconds')]),
        ], $warningMinutes * 60, $resumed) + self::ending($assessment));
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $assessment = $this->current($request);

        if ($refusal = LivePage::refusal($assessment)) {
            return $refusal;
        }

        $assessment->appendTranscript($request->transcript());
        $assessment->recordSignal($request->inputTokens(), $request->outputTokens(), $request->connected(), $request->tokenDetails());
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

        if ($refusal = LivePage::refusal($assessment)) {
            return $refusal;
        }

        // Un seul résultat, pendant l'évaluation ou juste après sa coupure.
        $tooLate = ! $assessment->isInProgress()
            && ($assessment->ended_at === null || $assessment->ended_at->lt(now()->subMinutes(2)));

        if ($assessment->level !== null || $tooLate) {
            return $this->refuse('Le résultat de cette évaluation est déjà enregistré.', 409);
        }

        // Niveau demandé trop tôt (deux ou trois questions seulement) : la PREMIÈRE fois, rien n'est enregistré et
        // le professeur pose encore quelques questions ; une seconde demande est acceptée.
        if ($early = LiveEarlyEnd::assessment($assessment)) {
            return response()->json($early);
        }

        $assessment->recordResult($request->level(), $request->feedback());

        if (! $assessment->isInProgress()) {
            $assessment->update(['status' => AssessmentStatus::Completed]);
        }

        // Moment des questions libres et conclusion (seulement si la conversation est encore ouverte).
        $assessment->startQuestions();
        // Message vocal du résultat : prêt quand l'apprenant arrive sur la page de résultat.
        LevelMessage::queue($assessment->level);

        return response()->json([
            'level' => $assessment->level->value,
            'label' => $assessment->level->label(),
            'remaining_seconds' => $assessment->remainingSeconds(),
        ] + self::ending($assessment) + self::discovery($assessment, $request->lesson()));
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $assessment = $this->current($request);

        if ($refusal = LivePage::refusal($assessment)) {
            return $refusal;
        }

        $assessment->appendTranscript($request->transcript());
        $assessment->recordSignal($request->inputTokens(), $request->outputTokens(), null, $request->tokenDetails());

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
            // Après l'indication de conclusion, fin si le professeur ne dit plus rien ce temps (jamais de séance muette).
            'silent_end_seconds' => (int) config('platform.assessment.silent_end_seconds'),
            // Niveau montré quand le professeur le dit (écran et vidéo), jamais avant : délai de sécurité et maximum.
            'level_reveal_delay_seconds' => (float) config('platform.assessment.level_reveal_delay_seconds'),
            'level_reveal_max_seconds' => (float) config('platform.assessment.level_reveal_max_seconds'),
        ];
    }

    /**
     * Mini-leçon de fin (seulement si la conversation continue après le verdict), montrée au tableau, et la suite
     * donnée au professeur (réponse de la fonction : aucun nouvel appel, mêmes consignes chez Gemini et chez OpenAI).
     * PERSONNELLE : les une ou deux vraies erreurs de l'apprenant transmises avec le verdict (ou une tournure plus
     * naturelle du niveau supérieur) ; en secours seulement, quelques phrases de la première leçon du niveau trouvé.
     *
     * @param  list<array{said: string, correct: string, why: string}>  $lesson
     * @return array<string, mixed>
     */
    private static function discovery(Assessment $assessment, array $lesson = []): array
    {
        if (! $assessment->inQuestions()) {
            return [];
        }

        return AssessmentMiniLesson::for($assessment->level, $lesson);
    }

    private function resultPage(Request $request, Assessment $assessment): View
    {
        $user = $request->user();
        $hasSubscription = $user->activeSubscription()->exists();
        // « Voici votre première leçon » (sans abonnement) : tableau de l'unité 1, leçon 1 du niveau de l'apprenant,
        // déjà préparé ou de secours ; s'il manque, préparé après la réponse pour les prochains (jamais pendant l'affichage).
        $lessonLevel = $user->level ?? $assessment->level;
        $firstLesson = ! $hasSubscription && $lessonLevel !== null ? LessonBoards::firstLesson($lessonLevel) : null;

        if ($firstLesson !== null && $firstLesson['source'] === 'programme') {
            LessonBoards::queueFirstLesson($lessonLevel);
        }

        return view('assessment.result', [
            'assessment' => $assessment,
            'found' => $assessment->level,
            'chosen' => $user->level,
            'levels' => Level::cases(),
            'rank' => array_flip(array_map(fn (Level $level) => $level->value, Level::cases())),
            'canChoose' => ! $user->programs()->exists(),
            'plans' => Plan::all(),
            'hasSubscription' => $hasSubscription,
            'firstLesson' => $firstLesson,
            'lessonLevel' => $lessonLevel,
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
