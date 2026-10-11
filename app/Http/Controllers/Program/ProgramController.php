<?php

namespace App\Http\Controllers\Program;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Program\QuestionnaireRequest;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\ProgramBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Questionnaire en 4 écrans, puis programme personnel (calendrier, unité en cours, progression).
 */
class ProgramController extends Controller
{
    public const STEPS = 4;

    private const SESSION_KEY = 'program_questionnaire';

    public function show(Request $request): View|RedirectResponse
    {
        $program = $request->user()->activeProgram;

        if ($program === null) {
            return to_route('program.questionnaire', 1);
        }

        $lessons = $program->programLessons()->get()->each->setRelation('program', $program);
        $current = $lessons->first(fn ($lesson) => $lesson->status === ProgramLessonStatus::Pending);

        return view('program.show', [
            'program' => $program,
            'units' => $program->units(),
            'levelTitle' => Curriculum::title($program->level),
            'lessons' => $lessons,
            'byUnit' => $lessons->groupBy('unit_number'),
            'current' => $current,
            'upcoming' => $lessons->where('status', ProgramLessonStatus::Pending)->take(10),
            'progress' => $program->progressPercent(),
        ]);
    }

    /**
     * Détail d'une unité du programme officiel (objectifs, vocabulaire, grammaire…).
     */
    public function unit(Request $request, int $number): View
    {
        $program = $request->user()->activeProgram ?? abort(404);
        abort_unless($number >= 1 && $number <= Curriculum::UNITS_PER_LEVEL, 404);

        return view('program.unit', [
            'program' => $program,
            'unit' => $program->unit($number),
        ]);
    }

    public function questionnaire(Request $request, int $step): View|RedirectResponse
    {
        if ($redirect = $this->guard($request->user())) {
            return $redirect;
        }

        abort_unless($step >= 1 && $step <= self::STEPS, 404);

        $answers = $request->session()->get(self::SESSION_KEY, []);

        // On ne saute pas d'étape : chaque écran a besoin des précédents.
        if ($step > 1 && ! isset($answers[$step - 1])) {
            return to_route('program.questionnaire', 1);
        }

        return view('program.questionnaire', [
            'step' => $step,
            'steps' => self::STEPS,
            'answers' => array_merge(...array_values($answers ?: [[]])),
            'goals' => Goal::cases(),
            'difficulties' => Difficulty::cases(),
            'days' => [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'],
            'level' => $request->user()->level ?? Level::A1,
        ]);
    }

    public function saveStep(QuestionnaireRequest $request, int $step, ProgramBuilder $builder): RedirectResponse
    {
        $user = $request->user();

        if ($redirect = $this->guard($user)) {
            return $redirect;
        }

        abort_unless($step >= 1 && $step <= self::STEPS, 404);

        $answers = $request->session()->get(self::SESSION_KEY, []);
        $answers[$step] = $request->validated();
        $request->session()->put(self::SESSION_KEY, $answers);

        if ($step < self::STEPS) {
            return to_route('program.questionnaire', $step + 1);
        }

        $all = array_merge(...array_values($answers));

        if (! isset($all['goal'], $all['occupation'], $all['study_days'], $all['difficulties'])) {
            return to_route('program.questionnaire', 1);
        }

        $builder->build($user, $user->level ?? Level::A1, $all);
        $request->session()->forget(self::SESSION_KEY);

        return to_route('program.show')->with('status', 'Votre programme personnel est prêt !');
    }

    /**
     * Le questionnaire suit le premier paiement validé, et ne sert qu'une fois par niveau.
     */
    private function guard(User $user): ?RedirectResponse
    {
        if ($user->activeSubscription === null) {
            return to_route('payment.plans')->with('status', 'Votre programme personnel se construit après la validation de votre premier paiement.');
        }

        if ($user->activeProgram !== null) {
            return to_route('program.show');
        }

        return null;
    }
}
