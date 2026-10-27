<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Admin\AdminRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Page du lien de récupération de l'administrateur : le jeton (après « # ») est lu par la page
 * et renvoyé en POST ; il sert une fois, puis le site exige un nouveau mot de passe.
 */
class AdminRecoveryController extends Controller
{
    public function show(): View
    {
        return view('auth.admin-recovery');
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = AdminRecovery::consume((string) $request->input('token'));

        if ($admin === null) {
            return redirect()->route('admin.recovery')->withErrors([
                'token' => 'Ce lien ne marche plus : il a déjà servi, il a expiré ou il a été remplacé par un lien plus récent. Demandez-en un nouveau.',
            ]);
        }

        Auth::guard('web')->logout();
        Auth::login($admin);
        $request->session()->regenerate();

        $admin->must_change_password = true;
        $admin->setRememberToken(null);
        $admin->save();

        return redirect()->route('password.change');
    }
}
