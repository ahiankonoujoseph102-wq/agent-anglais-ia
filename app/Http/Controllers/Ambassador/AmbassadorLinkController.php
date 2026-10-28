<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use App\Support\Ambassadors\AmbassadorReferrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lien personnel d'un ambassadeur (talkami.net/a/CODE, aussi derrière son code QR) : garde le code pour
 * l'inscription (premier lien utilisé, platform.ambassadors.link_days jours), puis mène à l'accueil, même avec
 * un code inconnu (jamais de page d'erreur pour un visiteur). Un compte déjà connecté n'est rattaché à personne.
 */
class AmbassadorLinkController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if ($request->user() === null) {
            AmbassadorReferrals::rememberLink($request, $code);
        }

        return redirect()->route('home');
    }
}
