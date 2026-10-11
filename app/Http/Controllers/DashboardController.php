<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
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

        $subscription = $user->activeSubscription;
        $latestSubscription = $user->latestSubscription;
        $latestPayment = $user->latestPayment;

        // Rappel de renouvellement : fin proche et aucun abonnement suivant déjà payé.
        $renewalDue = $subscription !== null
            && $latestSubscription?->is($subscription)
            && $subscription->ends_at->lte(now()->addDays((int) config('payment.renewal_reminder_days')));

        return view('dashboard', [
            'user' => $user,
            'assessment' => $assessment,
            'canTakeAssessment' => $canTakeAssessment,
            'subscription' => $subscription,
            'nextSubscription' => $subscription !== null && ! $latestSubscription?->is($subscription) ? $latestSubscription : null,
            'expiredSubscription' => $subscription === null ? $latestSubscription : null,
            'renewalDue' => $renewalDue,
            'pendingPayment' => $latestPayment?->status === PaymentStatus::Pending ? $latestPayment : null,
            'rejectedPayment' => $latestPayment?->status === PaymentStatus::Rejected ? $latestPayment : null,
            'remainingSeconds' => $user->remainingSecondsToday(),
            'minStartSeconds' => (int) config('platform.live.min_start_seconds'),
            'lastLesson' => $user->lessons()->whereNotNull('summary')->latest('id')->first(),
            'assessmentMinutes' => (int) config('platform.assessment.max_minutes'),
        ]);
    }
}
