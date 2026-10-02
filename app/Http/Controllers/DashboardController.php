<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'subscription' => $user->activeSubscription,
            'remainingSeconds' => $user->remainingSecondsToday(),
            'assessmentMinutes' => (int) config('platform.assessment.max_minutes'),
        ]);
    }
}
