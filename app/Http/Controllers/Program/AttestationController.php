<?php

namespace App\Http\Controllers\Program;

use App\Http\Controllers\Controller;
use App\Models\Attestation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttestationController extends Controller
{
    /**
     * Téléchargement de l'attestation (PDF) par son titulaire ou un administrateur.
     */
    public function download(Request $request, Attestation $attestation): Response
    {
        abort_unless($attestation->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        $filename = 'attestation-anglais-'.$attestation->level->value.'-'.Str::slug($attestation->user->name).'.pdf';

        return response($attestation->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Vérification publique d'une attestation à partir du code imprimé dessus.
     */
    public function verify(string $code): View
    {
        $attestation = Attestation::query()->with('user')->where('code', strtoupper($code))->first();

        return view('attestations.verify', ['attestation' => $attestation, 'code' => $code]);
    }
}
