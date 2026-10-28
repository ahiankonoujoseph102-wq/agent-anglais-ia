<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use App\Models\Ambassador;
use App\Support\Ambassadors\AmbassadorCommissions;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Ambassadors\AmbassadorReferrals;
use App\Support\Ambassadors\QrCode;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Espace ambassadeur (ambassadeur ACTIF seulement, sinon 404) : lien, code et code QR personnels, mois gratuit en
 * cours, régularité des séances.
 */
class AmbassadorSpaceController extends Controller
{
    public function show(Request $request): View
    {
        $ambassador = $this->ambassador($request);
        $grant = $ambassador->currentGrant();
        AmbassadorReferrals::noteAmbassador($request->user(), $request);

        return view('ambassador.show', [
            'ambassador' => $ambassador,
            'grant' => $grant,
            'monthLabel' => AmbassadorProgram::monthLabel($ambassador, $grant),
            'activity' => AmbassadorProgram::activity([$ambassador->user_id])[$ambassador->user_id],
            'qr' => QrCode::svg($ambassador->link(), 'Code QR de votre lien personnel'),
            'shareUrl' => WhatsApp::url('', self::shareMessage($ambassador)),
            // Prénom et date d'inscription seulement : jamais le numéro ni le nom complet.
            'referrals' => $ambassador->referrals()->with('user:id,name')->latest()->get(),
            'commissions' => $ambassador->commissions()->with('user:id,name')->latest()->get(),
            'totals' => AmbassadorCommissions::totals($ambassador),
        ]);
    }

    /** Image PNG du code QR, avec le lien et le code écrits dessous (à imprimer ou à envoyer). */
    public function qr(Request $request): Response
    {
        $ambassador = $this->ambassador($request);
        $png = QrCode::png($ambassador->link(), [$ambassador->shortLink(), 'Code : '.$ambassador->code]);

        abort_if($png === null, 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.strtolower((string) config('platform.name')).'-code-qr-'.strtolower($ambassador->code).'.png"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Message prérempli du partage WhatsApp : des faits du site seulement, aucune promesse. */
    public static function shareMessage(Ambassador $ambassador): string
    {
        return strtr(
            'J\'apprends à parler anglais avec :teacher sur :platform. Passe toi aussi ton évaluation orale gratuite de :minutes minutes, depuis ton téléphone : :url',
            [
                ':teacher' => (string) config('avatar.model.name'),
                ':platform' => (string) config('platform.name'),
                ':minutes' => (string) config('platform.assessment.max_minutes'),
                ':url' => $ambassador->link(),
            ],
        );
    }

    private function ambassador(Request $request): Ambassador
    {
        $ambassador = $request->user()->ambassador;

        abort_unless($ambassador?->isActive() === true, 404);

        return $ambassador;
    }
}
