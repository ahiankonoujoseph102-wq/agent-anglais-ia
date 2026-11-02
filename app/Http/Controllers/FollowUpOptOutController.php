<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Ne plus recevoir ces e-mails » (lien signé des e-mails de relance, sans connexion) : la page demande une
 * confirmation (un logiciel qui ouvre les liens d'un e-mail ne désinscrit personne), puis plus aucune relance.
 */
class FollowUpOptOutController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('follow-ups.stop', ['user' => $user, 'done' => $user->follow_up_opt_out_at !== null, 'action' => $request->fullUrl()]);
    }

    public function store(Request $request, User $user): View
    {
        if ($user->follow_up_opt_out_at === null) {
            $user->forceFill(['follow_up_opt_out_at' => now()])->save();
        }

        return view('follow-ups.stop', ['user' => $user, 'done' => true, 'action' => $request->fullUrl()]);
    }
}
