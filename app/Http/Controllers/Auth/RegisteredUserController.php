<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorReferrals;
use App\Support\Origins;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            // Code du lien d'un ambassadeur déjà ouvert : proposé dans le champ, la personne peut le changer.
            'suggestedCode' => AmbassadorReferrals::suggestedCode($request),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->except('ambassador_code'));
        // Provenance (page d'arrivée, site d'origine, lien partagé) gardée en session depuis la première page vue.
        Origins::record($user, $request->session()->pull(Origins::SESSION));
        // Ambassadeur : le code tapé passe avant tout, sinon le premier lien utilisé (30 jours).
        AmbassadorReferrals::record($user, $request, $request->validated('ambassador_code'));

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Bienvenue '.$user->name.' ! Votre compte est créé.');
    }
}
