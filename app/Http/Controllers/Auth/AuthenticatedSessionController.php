<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        // Ambassadeur : repère de son appareil et de son réseau (signaux d'une inscription faite chez lui).
        AmbassadorReferrals::noteAmbassador($request->user(), $request);

        return redirect()->intended(self::homeFor($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Page d'arrivée d'un utilisateur connecté selon son rôle.
     */
    public static function homeFor(?User $user): string
    {
        return $user?->isStaff() ? route('admin.payments.pending') : route('dashboard');
    }
}
