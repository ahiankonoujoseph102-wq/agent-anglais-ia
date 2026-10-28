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
use App\Support\Curriculum\LessonBoards;
use App\Support\Curriculum\LessonContext;
use App\Support\Curriculum\LessonPlan;
use App\Support\Curriculum\LessonReport;
use App\Support\Curriculum\SpacedReview;
use App\Support\DailyTime;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\LiveEarlyEnd;
use App\Support\Live\LivePage;
use App\Support\Live\WorkingTogether;
use App\Support\Text\TextProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * La séance du jour des abonnés, limitée au temps restant du jour de leur formule (partagé avec « Mes cours »).
 */
class LessonController extends Controller
{
    private const NO_SUBSCRIPTION = 'Votre abonnement n\'est pas actif : choisissez une formule pour suivre vos séances.';

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

        // Moins de 2 minutes dans le temps du jour (partagé avec « Mes cours ») : pas de nouvelle séance.
        if ($lesson === null && $remaining < DailyTime::minStartSeconds()) {
            return to_route('dashboard')->with('status', DailyTime::EXHAUSTED);
        }

        $board = $this->board($user, $lesson);

        return view('live.session', [
            'mode' => 'lesson',
            // Phrases utiles dépliées aux premières séances, repliées ensuite.
            'usefulPhrasesOpen' => WorkingTogether::isFirst(WorkingTogether::lessonNumber($user, $lesson)),
            'title' => 'Ma séance du jour',
            // UNE ligne courte sous « Commencer » ; le détail est replié sous « Avant de commencer ».
            'intro' => DailyTime::remainingSentence($remaining),
            'introMore' => 'Ce temps du jour est partagé avec « Mes cours ». Le professeur vous annoncera la durée, puis vous préviendra deux minutes avant la fin.',
            'remainingSeconds' => $remaining,
            'board' => $board,
            // Traductions du tableau : visibles en A1 et A2, discrètes en B1, masquées ensuite.
            'boardTranslations' => match ($user->activeProgram()->first()?->level ?? $user->level) {
                Level::A1, Level::A2, null => 'show',
                Level::B1 => 'discreet',
                default => 'hidden',
            },
            'urls' => [
                'connect' => route('lesson.connect'),
                'signal' => route('lesson.signal'),
                'finish' => route('lesson.finish'),
                'result' => route('lesson.report'),
                'done' => route('dashboard'),
            ],
        ]);
    }

    /**
     * Verrou commun à la séance orale et à « Mes cours » : deux ouvertures en même temps ne prennent
     * jamais plus que le temps du jour.
     */
    public static function connectLockKey(User $user): string
    {
        return 'live-connect:'.$user->id;
    }

    public function connect(ConnectRequest $request, LiveConnection $live): JsonResponse
    {
        $lock = Cache::lock(self::connectLockKey($request->user()), 20);

        if (! $lock->get()) {
            return $this->refuse('Une connexion est déjà en cours. Patientez un instant, puis réessayez.', 429);
        }

        try {
            return $this->start($request, $live);
        } finally {
            $lock->release();
        }
    }

    private function start(ConnectRequest $request, LiveConnection $live): JsonResponse
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

        if ($lesson === null && $allowedSeconds < DailyTime::minStartSeconds()) {
            return $this->refuse(DailyTime::EXHAUSTED, 403);
        }

        $deadline = $lesson?->deadline() ?? now()->addSeconds($allowedSeconds);
        // La leçon du programme : celle de la séance reprise, sinon la prochaine à faire.
        $program = $user->activeProgram()->first();
        $programLesson = $lesson?->programLesson ?? $program?->currentLesson()->first();
        $programLesson?->setRelation('program', $program);

        $minutes = (int) ceil($allowedSeconds / 60);
        $plan = LessonPlan::for($program?->level ?? $user->level, $minutes);
        $setup = TeacherSetup::lesson($user, $minutes, $request->handle(), $this->context($user, $programLesson, $plan),
            WorkingTogether::lessonNumber($user, $lesson));

        try {
            $token = $live->request($setup, $deadline, $lesson, $request->failedProvider(), $request->unresponsiveProvider());
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
            'start' => TeacherSetup::cue('lesson_start', ['minutes' => $minutes, 'duration_note' => self::durationNote($minutes, $subscription?->daily_minutes ?? $minutes)]),
            'resume' => TeacherSetup::cue('resume'),
            'warning' => TeacherSetup::cue('lesson_warning', ['remaining' => $warningMinutes]),
            // Séance en quatre temps : reprise selon l'étape en cours, et une seule relance douce vers la conversation.
            'resume_phases' => self::phaseCues('lesson_resume'),
            'pause_phases' => self::phaseCues('lesson_pause_resume'),
            'conversation' => TeacherSetup::cue('lesson_conversation'),
        ], $warningMinutes * 60, $resumed) + [
            // Écran : étape en cours d'après les phrases de passage du professeur, sinon d'après le temps écoulé.
            'phases' => [
                'phrases' => (array) config('platform.lessons.phase_phrases'),
                'starts' => LessonPlan::starts($plan),
                'merged' => $plan['merged'],
                // Durée accordée à la séance : temps écoulé = durée − temps restant.
                'total' => $lesson->allowed_seconds,
            ],
        ]);
    }

    public function signal(SignalRequest $request): JsonResponse
    {
        $lesson = $this->latestLesson($request->user());

        if ($refusal = LivePage::refusal($lesson)) {
            return $refusal;
        }

        $lesson->recordSignal($request->inputTokens(), $request->outputTokens(), $request->connected(), $request->tokenDetails());
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

        if ($refusal = LivePage::refusal($lesson)) {
            return $refusal;
        }

        // Un seul bilan par séance, envoyé pendant la séance ou juste après sa fin.
        if ($lesson->reported_at !== null || ($lesson->ended_at !== null && $lesson->ended_at->lt(now()->subMinutes(5)))) {
            return $this->refuse('Le bilan de cette séance est déjà enregistré.', 409);
        }

        // Bilan demandé trop tôt (le professeur avait parcouru la leçon trop vite) : la PREMIÈRE fois, rien n'est
        // enregistré et le professeur reprend la séance ; une seconde demande (l'élève veut vraiment arrêter) est
        // acceptée.
        if ($early = LiveEarlyEnd::lesson($lesson)) {
            return response()->json($early);
        }

        LessonReport::record($lesson, $request->validated());
        $this->prepareNextBoard($request->user());

        return response()->json([
            'saved' => true,
            'outcome' => $lesson->outcome,
            'program_status' => $lesson->programLesson?->program->status->value,
        ]);
    }

    public function finish(SignalRequest $request): JsonResponse
    {
        $lesson = $this->latestLesson($request->user());

        if ($refusal = LivePage::refusal($lesson)) {
            return $refusal;
        }

        $lesson->recordSignal($request->inputTokens(), $request->outputTokens(), null, $request->tokenDetails());

        if ($lesson->closeIfOver()) {
            $lesson->finish(now());
        }

        $this->prepareNextBoard($request->user());

        return response()->json(['status' => $lesson->status->label()]);
    }

    /**
     * Séance plus courte que le temps du jour de la formule (une partie est déjà prise aujourd'hui) : le professeur
     * le dit, pour que la durée annoncée ne surprenne pas (« 7 minutes » un jour, « 15 minutes » le lendemain).
     */
    private static function durationNote(int $minutes, int $dailyMinutes): string
    {
        return $minutes < $dailyMinutes
            ? ', and add in French: « C\'est le temps qu\'il vous reste aujourd\'hui. »'
            : '';
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
    private function context(User $user, ?ProgramLesson $programLesson, array $plan): ?string
    {
        if ($programLesson === null) {
            return null;
        }

        $previous = $user->lessons()->whereNotNull('reported_at')->latest('id')->first();
        $errors = $user->openErrors()->limit((int) config('program.errors_per_lesson'))->get();

        // Tableau déjà préparé, sinon celui de secours : jamais de préparation au moment de la connexion.
        return LessonContext::describe($user, $programLesson->program, $programLesson, $previous, $errors,
            LessonBoards::for($programLesson, $programLesson->program), $plan,
            SpacedReview::phrases($user, $programLesson->program, $programLesson));
    }

    /**
     * Une indication par étape de la séance (reprise après une coupure ou une pause).
     *
     * @return array<string, string>
     */
    private static function phaseCues(string $key): array
    {
        $names = [
            'lesson' => 'STEP 1 (lesson: teaching the board)',
            'practice' => 'STEP 2 (guided practice)',
            'conversation' => 'STEP 3 (conversation: the role play)',
            'summary' => 'STEP 4 (summary)',
        ];

        return array_map(fn (string $name) => TeacherSetup::cue($key, ['phase' => $name]), $names);
    }

    /**
     * Tableau de la leçon du jour (celle de la séance reprise, sinon la prochaine à faire). S'il n'est pas encore
     * prêt, il est préparé ici avec un délai court (jamais à la connexion) ; sinon, tableau de secours du programme.
     *
     * @return array<string, mixed>|null
     */
    private function board(User $user, ?Lesson $lesson): ?array
    {
        $program = $user->activeProgram()->first();
        $programLesson = $lesson?->programLesson ?? $program?->currentLesson()->first();

        if ($program === null || $programLesson === null) {
            return null;
        }

        $programLesson->setRelation('program', $program);

        if (! LessonBoards::ready($programLesson, $program) && TextProviders::available() !== []) {
            try {
                LessonBoards::prepare($programLesson, $program, (int) config('program.board_prepare_timeout'));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return LessonBoards::for($programLesson, $program);
    }

    /**
     * Après une séance : le tableau de la leçon suivante est préparé après la réponse.
     */
    private function prepareNextBoard(User $user): void
    {
        $program = $user->activeProgram()->first();
        $next = $program?->currentLesson()->first();
        $next?->setRelation('program', $program);

        LessonBoards::queue($next, $program);
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
