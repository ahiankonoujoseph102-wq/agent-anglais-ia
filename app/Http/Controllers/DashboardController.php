<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $assessment = $user->assessment;

        // Une évaluation encore ouverte peut être reprise tant que son temps n'est pas écoulé.
        $canTakeAssessment = $assessment === null || $assessment->closeIfOver();

        return view('dashboard', [
            'user' => $user,
            'assessment' => $assessment,
            'canTakeAssessment' => $canTakeAssessment,
            'subscription' => $user->activeSubscription,
            'remainingSeconds' => $user->remainingSecondsToday(),
            'minStartSeconds' => (int) config('platform.live.min_start_seconds'),
            'lastLesson' => $user->lessons()->whereNotNull('summary')->latest('id')->first(),
            'assessmentMinutes' => (int) config('platform.assessment.max_minutes'),
        ]);
    }
}
