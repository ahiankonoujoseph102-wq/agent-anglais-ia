<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ambassador\AmbassadorApplicationRequest;
use App\Support\Ambassadors\AmbassadorApplications;
use App\Support\Ambassadors\AmbassadorCommissions;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Ambassadors\AmbassadorRefused;
use App\Support\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page publique « Devenir ambassadeur » : compteur réel des places, candidature (compte obligatoire) ou, quand
 * tout est pris, formulaire fermé et liste d'attente.
 */
class AmbassadorJoinController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $state = AmbassadorApplications::stateFor($user);
        $plan = Plan::find((string) config('platform.ambassadors.plan'));

        return view('ambassador.join', [
            'state' => $state,
            'places' => AmbassadorProgram::places(),
            'free' => AmbassadorProgram::freePlaces(),
            'plan' => $plan,
            'examples' => collect(Plan::all())->map(fn (Plan $item) => ['name' => $item->name, 'amount' => AmbassadorCommissions::amountFor($item->price)])->values(),
            'application' => $user?->ambassadorApplication,
        ]);
    }

    /** Un visiteur qui touche « Se connecter pour poser ma candidature » revient ici après la connexion. */
    public function form(): RedirectResponse
    {
        return redirect()->to(route('ambassador.join').'#candidature');
    }

    public function apply(AmbassadorApplicationRequest $request): RedirectResponse
    {
        try {
            AmbassadorApplications::apply($request->user(), $request->application());
        } catch (AmbassadorRefused $refused) {
            return $this->back()->withErrors(['application' => $refused->getMessage()]);
        }

        return $this->back()->with('status', 'Votre candidature est envoyée. Notre équipe l\'examine et vous répond sur WhatsApp.');
    }

    public function joinWaitlist(Request $request): RedirectResponse
    {
        try {
            AmbassadorApplications::joinWaitlist($request->user());
        } catch (AmbassadorRefused $refused) {
            return $this->back()->withErrors(['application' => $refused->getMessage()]);
        }

        return $this->back()->with('status', 'C\'est noté : nous vous prévenons sur WhatsApp dès qu\'une place se libère.');
    }

    public function leaveWaitlist(Request $request): RedirectResponse
    {
        AmbassadorApplications::leaveWaitlist($request->user());

        return $this->back()->with('status', 'Vous n\'êtes plus sur la liste d\'attente.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->to(route('ambassador.join').'#candidature');
    }
}
