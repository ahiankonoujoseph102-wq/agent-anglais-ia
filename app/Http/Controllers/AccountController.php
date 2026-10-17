<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Compte de l'apprenant : adresse e-mail facultative (la connexion reste par téléphone).
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->email = $request->validated('email');
        $user->save();

        return to_route('account.edit')->with('status', $user->email === null
            ? 'Votre compte est enregistré, sans adresse e-mail.'
            : 'Votre adresse e-mail est enregistrée.');
    }
}
