<?php

namespace App\Http\Controllers\Program;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Profile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Program\QuestionnaireRequest;
use App\Models\Program;
use App\Models\User;
use App\Support\Curriculum\ProgramBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * « Modifier mes objectifs » : le même questionnaire en 4 écrans, pré-rempli avec les réponses du programme
 * actif. À l'enregistrement, seules les leçons à venir changent (ProgramBuilder::update) : la progression,
 * le niveau, l'historique, le temps du jour, l'abonnement et les attestations restent tels quels.
 * Au plus program.answer_edits_per_day modifications par jour et par compte.
 */
class ProgramAnswersController extends Controller
{
    private const SESSION_KEY = 'program_answers_edit';

    public function edit(Request $request, Program $program, int $step): View|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProgram($user, $program);
        abort_unless($step >= 1 && $step <= ProgramController::STEPS, 404);

        if ($limited = $this->limited($user)) {
            return $limited;
        }

        // Première ouverture : les réponses actuelles du programme remplissent les 4 écrans.
        $answers = $request->session()->get(self::SESSION_KEY.'.'.$program->id);

        if ($answers === null) {
            $answers = self::current($program);
            $request->session()->put(self::SESSION_KEY.'.'.$program->id, $answers);
        }

        return view('program.questionnaire', [
            'step' => $step,
            'steps' => ProgramController::STEPS,
            'answers' => array_merge(...array_values($answers)),
            'goals' => Goal::cases(),
            'profiles' => Profile::cases(),
            'difficulties' => Difficulty::cases(),
            'days' => ProgramController::DAYS,
            'level' => $program->level,
            'editing' => $program,
        ]);
    }

    public function update(QuestionnaireRequest $request, Program $program, int $step, ProgramBuilder $builder): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProgram($user, $program);
        abort_unless($step >= 1 && $step <= ProgramController::STEPS, 404);

        if ($limited = $this->limited($user)) {
            return $limited;
        }

        $key = self::SESSION_KEY.'.'.$program->id;
        $answers = $request->session()->get($key, self::current($program));
        $answers[$step] = $request->validated();
        $request->session()->put($key, $answers);

        if ($step < ProgramController::STEPS) {
            return to_route('program.answers.edit', [$program, $step + 1]);
        }

        if (! $builder->update($user, $program, array_merge(...array_values($answers)))) {
            // Rien n'a changé : les réponses restent en mémoire pour réessayer.
            return to_route('program.show')->with('status', 'Nous n\'avons pas pu mettre à jour votre programme pour le moment. Votre programme actuel est gardé, sans aucune perte : réessayez dans quelques minutes.');
        }

        RateLimiter::hit(self::limiterKey($user), (int) now()->diffInSeconds(now()->endOfDay(), true) + 1);
        $request->session()->forget($key);

        return to_route('program.show')->with('status', 'Vos objectifs sont enregistrés. '.config('avatar.model.name').' en tiendra compte dès votre prochaine séance.');
    }

    /**
     * Réponses actuelles du programme, rangées par écran comme dans le questionnaire.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function current(Program $program): array
    {
        return [
            1 => ['goal' => $program->goal->value],
            2 => ['profile' => $program->profile?->value, 'occupation' => $program->occupation, 'interests' => $program->interests],
            3 => ['study_days' => array_map('intval', (array) $program->study_days), 'study_time' => $program->study_time],
            4 => ['difficulties' => array_values((array) $program->difficulties)],
        ];
    }

    public static function limiterKey(User $user): string
    {
        return 'program-answers:'.$user->id;
    }

    /**
     * Seul le programme ACTIF de l'apprenant se modifie.
     */
    private function authorizeProgram(User $user, Program $program): void
    {
        abort_unless($program->user_id === $user->id && $program->is_active, 403);
    }

    private function limited(User $user): ?RedirectResponse
    {
        $max = (int) config('program.answer_edits_per_day');

        if (! RateLimiter::tooManyAttempts(self::limiterKey($user), $max)) {
            return null;
        }

        return to_route('program.show')->with('status', 'Vous avez déjà modifié vos objectifs '.$max.' fois aujourd\'hui. Vous pourrez les modifier de nouveau demain.');
    }
}
