<?php

namespace App\Http\Controllers\Program;

use App\Enums\ErrorStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des séances (résumé, devoir, notes) et erreurs en cours.
 */
class LessonHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('program.history', [
            'lessons' => $user->lessons()
                ->whereNotNull('started_at')
                ->with('programLesson')
                ->latest('id')
                ->paginate(15),
            'openErrors' => $user->openErrors()->get(),
            'resolvedCount' => $user->learnerErrors()->where('status', ErrorStatus::Resolved)->count(),
        ]);
    }
}
