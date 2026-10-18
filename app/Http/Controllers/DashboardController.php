<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Support\WelcomeFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Bienvenue de Teacher Joe : à la première ouverture d'un accès (une seule fois).
        if ($welcome = WelcomeFlow::redirect($user)) {
            return $welcome;
        }

        $assessment = $user->assessment;

        // Une évaluation encore ouverte peut être reprise tant que son temps n'est pas écoulé.
        $canTakeAssessment = $assessment === null || $assessment->closeIfOver();
        // Jamais passée, réinitialisée par l'administrateur ou en cours : elle passe avant tout le reste.
        $needsAssessment = $canTakeAssessment;

        $subscription = $user->activeSubscription;
        $latestSubscription = $user->latestSubscription;
        $latestPayment = $user->latestPayment;

        // Accès offert par l'administrateur : « Accès offert jusqu'au … », et un message discret le jour de la fin et la veille.
        $granted = $subscription?->isGranted() ? $subscription : null;
        $grantEnding = $granted !== null
            && $granted->ends_at->copy()->startOfDay()->lte(now()->addDays((int) config('platform.granted_access.reminder_days'))->startOfDay());

        // Rappel de renouvellement (abonnement payé) : fin proche et aucun abonnement suivant déjà payé.
        $renewalDue = $subscription !== null
            && $granted === null
            && $latestSubscription?->is($subscription)
            && $subscription->ends_at->lte(now()->addDays((int) config('payment.renewal_reminder_days')));

        $program = $user->activeProgram;

        // Étape du parcours : 1 évaluation, 2 formule (et paiement), 3 programme, 4 séances.
        $journeyStep = match (true) {
            $needsAssessment && $subscription === null => 1,
            $subscription === null => 2,
            $program === null && $user->attestations()->doesntExist() => 3,
            default => 4,
        };
        $current = $program?->currentLesson;
        $current?->setRelation('program', $program);

        return view('dashboard', [
            'user' => $user,
            'program' => $program,
            'currentLesson' => $current,
            'progress' => $program?->progressPercent(),
            'streak' => $user->streakDays(),
            'skills' => $user->skillAverages(),
            'openErrors' => $user->openErrors()->limit(5)->get(),
            'openErrorsCount' => $user->openErrors()->count(),
            'attestations' => $user->attestations()->orderBy('level')->get(),
            'lastLevelTest' => $user->levelTests()->latest('id')->first(),
            'assessment' => $assessment,
            'canTakeAssessment' => $canTakeAssessment,
            'needsAssessment' => $needsAssessment,
            'subscription' => $subscription,
            'nextSubscription' => $subscription !== null && ! $latestSubscription?->is($subscription) ? $latestSubscription : null,
            'expiredSubscription' => $subscription === null ? $latestSubscription : null,
            'renewalDue' => $renewalDue,
            'granted' => $granted,
            'grantEnding' => $grantEnding,
            'pendingPayment' => $latestPayment?->status === PaymentStatus::Pending ? $latestPayment : null,
            'rejectedPayment' => $latestPayment?->status === PaymentStatus::Rejected ? $latestPayment : null,
            'remainingSeconds' => $user->remainingSecondsToday(),
            'minStartSeconds' => (int) config('platform.live.min_start_seconds'),
            'lastLesson' => $user->lessons()->whereNotNull('summary')->latest('id')->first(),
            'journeyStep' => $journeyStep,
            // « Mes cours » : carte secondaire, seulement avec un accès actif.
            'courseCounter' => $subscription !== null ? ['used' => $user->courseSessionsToday(), 'limit' => (int) config('platform.courses.sessions_per_day')] : null,
            'assessmentMinutes' => (int) config('platform.assessment.max_minutes'),
            // « Réécouter le message de bienvenue » : seulement pour un apprenant qui a eu un accès.
            'welcomeReplay' => $user->subscriptions()->exists(),
        ]);
    }
}
