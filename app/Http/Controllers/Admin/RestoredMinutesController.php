<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Admin\RestoredMinutes;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Rendre des minutes aujourd'hui » (fiche d'un inscrit) : l'élève n'a pas pu faire sa séance, son temps du jour
 * est rendu tout de suite. Administrateur principal seulement (route hors de la liste des assistants).
 */
class RestoredMinutesController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:'.RestoredMinutes::MAX_MINUTES],
        ], [], ['minutes' => 'minutes']);

        $back = route('admin.users.show', $user).'#minutes';

        if ($refusal = RestoredMinutes::refusal($user)) {
            return redirect()->to($back)->withErrors(['minutes' => $refusal]);
        }

        RestoredMinutes::give($user, (int) $data['minutes']);
        $left = (int) $user->fresh()->remainingSecondsToday(false);

        return redirect()->to($back)->with('status', $data['minutes'].' minutes rendues. Il lui reste maintenant '.Duration::format($left).' aujourd\'hui.');
    }
}
