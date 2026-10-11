<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Mot de passe oublié (sans SMS ni e-mail payant) et changement obligatoire
 * après un mot de passe provisoire donné par l'administrateur.
 */
class PasswordController extends Controller
{
    /**
     * Explique la démarche et prépare un message WhatsApp avec le numéro du compte.
     */
    public function forgot(Request $request): View
    {
        $input = trim((string) $request->query('telephone'));
        $phone = $input !== '' ? PhoneNumber::normalize($input) : null;
        $whatsappUrl = null;

        if ($phone !== null) {
            $whatsappUrl = WhatsApp::url(config('platform.contact.whatsapp'), implode("\n", [
                'Bonjour, j\'ai oublié mon mot de passe '.config('platform.name').'.',
                'Numéro de mon compte : '.$phone,
                'Pouvez-vous me donner un mot de passe provisoire ?',
            ]));
        }

        return view('auth.forgot-password', [
            'input' => $input,
            'phone' => $phone,
            'invalid' => $input !== '' && $phone === null,
            'whatsappUrl' => $whatsappUrl,
        ]);
    }

    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $user = $request->user();
        $user->password = $request->input('password');
        $user->must_change_password = false;
        $user->save();

        $request->session()->regenerate();

        return redirect(AuthenticatedSessionController::homeFor($user))
            ->with('status', 'Votre nouveau mot de passe est enregistré.');
    }
}
