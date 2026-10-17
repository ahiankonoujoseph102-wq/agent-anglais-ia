<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfferAccessRequest;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Accès offert par l'administrateur (démonstrations) : offrir, retirer. Route « admin » uniquement.
 */
class GrantedAccessController extends Controller
{
    public function store(OfferAccessRequest $request, User $user): RedirectResponse
    {
        $subscription = AccessGrant::offer(
            $user,
            $request->user(),
            Plan::find($request->validated('plan')),
            $request->days(),
            $request->validated('note'),
        );

        return to_route('admin.users.show', $user)->with('status', 'Accès offert : formule '.$subscription->plan_name.' jusqu\'au '.$subscription->ends_at->translatedFormat('j F Y \à H:i').'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $revoked = AccessGrant::revoke($user);

        return to_route('admin.users.show', $user)->with('status', $revoked ? 'L\'accès offert est retiré.' : 'Aucun accès offert n\'était en cours.');
    }
}
