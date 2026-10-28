<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Lien personnel d'un ambassadeur (talkami.net/a/CODE, aussi derrière son code QR) : mène toujours à l'accueil,
 * même avec un code inconnu (jamais de page d'erreur pour un visiteur). La mémoire du lien (rattachement d'une
 * inscription à l'ambassadeur) arrive avec l'étape « suivi des inscrits ».
 */
class AmbassadorLinkController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        return redirect()->route('home');
    }
}
