<?php

namespace App\Http\Controllers\Program;

use App\Enums\ErrorStatus;
use App\Http\Controllers\Controller;
use App\Support\Curriculum\LessonBoards;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des séances (résumé, devoir, notes, tableau de la séance) et erreurs en cours.
 */
class LessonHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $lessons = $user->lessons()
            ->whereNotNull('started_at')
            ->with('programLesson.program')
            ->latest('id')
            ->paginate(15);

        return view('program.history', [
            'lessons' => $lessons,
            // Tableau de chaque séance, gardé pour réviser (le même qu'à l'écran ; jamais d'appel au modèle texte).
            'boards' => $lessons->getCollection()
                ->filter(fn ($lesson) => $lesson->programLesson?->program !== null)
                ->mapWithKeys(fn ($lesson) => [$lesson->id => LessonBoards::for($lesson->programLesson, $lesson->programLesson->program)]),
            'openErrors' => $user->openErrors()->get(),
            // Séances « Mes cours » finies et leur document de révision (les plus récentes d'abord).
            'courseSessions' => $user->courseSessions()->whereNotNull('ended_at')->with(['review', 'lesson'])->latest('id')->limit(10)->get(),
            'resolvedCount' => $user->learnerErrors()->where('status', ErrorStatus::Resolved)->count(),
        ]);
    }
}
