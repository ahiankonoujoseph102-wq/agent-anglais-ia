<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AmbassadorStatus;
use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Ambassador;
use App\Models\AmbassadorCommission;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorCommissions;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Ambassadors\AmbassadorReferrals;
use App\Support\Ambassadors\AmbassadorRefused;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Money;
use App\Support\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Onglet « Ambassadeurs » et section « Ambassadeur » de la fiche d'un inscrit : nommer, renouveler le mois gratuit,
 * retirer du programme, noter l'accord d'un parent. Toujours une décision de l'administrateur. Route « admin ».
 */
class AmbassadorController extends Controller
{
    public function index(): View
    {
        $ambassadors = Ambassador::query()
            ->with(['user', 'grants'])
            ->withCount(['referrals', 'referrals as flagged_count' => fn ($query) => $query->whereNotNull('flags')])
            ->orderByRaw('case when status = ? then 0 else 1 end', [AmbassadorStatus::Active->value])
            ->orderBy('created_at')
            ->get();

        return view('admin.ambassadors.index', [
            'ambassadors' => $ambassadors,
            'activity' => AmbassadorProgram::activity($ambassadors->pluck('user_id')->all()),
            'places' => AmbassadorProgram::places(),
            'activeCount' => AmbassadorProgram::activeCount(),
            // Commissions à payer, les plus anciennes d'abord (avec les signaux du rattachement et du paiement).
            'dueCommissions' => AmbassadorCommission::query()->where('status', CommissionStatus::Due)
                ->with(['ambassador.user', 'user.referral'])->oldest()->get(),
            'totals' => $ambassadors->mapWithKeys(fn (Ambassador $ambassador) => [$ambassador->id => AmbassadorCommissions::totals($ambassador)]),
        ]);
    }

    public function appoint(Request $request, User $user): RedirectResponse
    {
        try {
            $result = AmbassadorProgram::appoint($user, $request->user());
        } catch (AmbassadorRefused $refused) {
            return $this->back($request, $user)->withErrors(['ambassador' => $refused->getMessage()]);
        }

        if ($result['grant'] !== null) {
            // Accès ouvert : la salutation de bienvenue (son prénom) se prépare après la réponse.
            WelcomeMessage::queueOpening($user);
        }

        $message = 'Ambassadeur nommé, code '.$result['ambassador']->code.'. '
            .($result['grant'] !== null
                ? 'Mois gratuit ouvert jusqu\'au '.$result['grant']->ends_at->translatedFormat('j F Y').'.'
                : $result['notice']);

        return $this->back($request, $user)->with('status', $message);
    }

    public function renew(Request $request, Ambassador $ambassador): RedirectResponse
    {
        try {
            $grant = AmbassadorProgram::renew($ambassador, $request->user());
        } catch (AmbassadorRefused $refused) {
            return $this->back($request, $ambassador->user)->withErrors(['ambassador' => $refused->getMessage()]);
        }

        WelcomeMessage::queueOpening($ambassador->user);

        return $this->back($request, $ambassador->user)->with('status', 'Mois gratuit renouvelé : '.$ambassador->user->name.' a accès jusqu\'au '.$grant->ends_at->translatedFormat('j F Y').'.');
    }

    public function remove(Request $request, Ambassador $ambassador): RedirectResponse
    {
        AmbassadorProgram::remove($ambassador);

        return $this->back($request, $ambassador->user)->with('status', $ambassador->user->name.' est retiré du programme : sa place est libérée et son accès gratuit est arrêté.');
    }

    public function consent(Request $request, Ambassador $ambassador): RedirectResponse
    {
        $received = $request->boolean('received');
        AmbassadorProgram::setParentalConsent($ambassador, $received);

        return $this->back($request, $ambassador->user)->with('status', $received ? 'Accord écrit d\'un parent noté.' : 'Accord d\'un parent retiré.');
    }

    /**
     * Correction par l'administrateur : rattache le compte à un ambassadeur actif, ou retire le rattachement.
     */
    public function referral(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'ambassador_id' => ['nullable', 'integer', Rule::exists('ambassadors', 'id')->where('status', AmbassadorStatus::Active->value)],
        ], ['ambassador_id.exists' => 'Choisissez un ambassadeur actif.']);

        $ambassador = isset($data['ambassador_id']) ? Ambassador::query()->find($data['ambassador_id']) : null;

        if ($ambassador?->user_id === $user->id) {
            return $this->back($request, $user)->withErrors(['ambassador' => 'Un ambassadeur ne peut pas être rattaché à lui-même.']);
        }

        AmbassadorReferrals::correct($user, $ambassador);

        return $this->back($request, $user)->with('status', $ambassador
            ? 'Inscrit rattaché à '.$ambassador->user->name.' ('.$ambassador->code.').'
            : 'Rattachement à un ambassadeur retiré.');
    }

    /** « Commission payée » : versée par Mobile Money par l'administrateur, notée ici. */
    public function payCommission(Request $request, AmbassadorCommission $commission): RedirectResponse
    {
        try {
            AmbassadorCommissions::markPaid($commission, $request->user());
        } catch (AmbassadorRefused $refused) {
            return $this->back($request, $commission->ambassador->user)->withErrors(['ambassador' => $refused->getMessage()]);
        }

        return $this->back($request, $commission->ambassador->user)->with('status', 'Commission de '.Money::format($commission->amount).' notée comme payée à '.$commission->ambassador->user->name.'.');
    }

    /** Annulation (triche), toujours avec un motif. */
    public function cancelCommission(Request $request, AmbassadorCommission $commission): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [
            'reason.required' => 'Écrivez le motif de l\'annulation.',
            'reason.max' => 'Le motif ne peut pas dépasser 200 caractères.',
        ]);

        try {
            AmbassadorCommissions::cancel($commission, $data['reason']);
        } catch (AmbassadorRefused $refused) {
            return $this->back($request, $commission->ambassador->user)->withErrors(['ambassador' => $refused->getMessage()]);
        }

        return $this->back($request, $commission->ambassador->user)->with('status', 'Commission annulée.');
    }

    /** « Ajouter une commission » à la main (paiement hors des quatre pays, abonnement par WhatsApp). */
    public function addCommission(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys(Plan::all()))],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['plan.required' => 'Choisissez la formule payée.', 'plan.in' => 'Cette formule n\'existe pas.', 'note.max' => 'La note ne peut pas dépasser 200 caractères.']);

        try {
            $commission = AmbassadorCommissions::addManual($user, Plan::find($data['plan']), $request->user(), $data['note'] ?? null);
        } catch (AmbassadorRefused $refused) {
            return $this->back($request, $user)->withErrors(['ambassador' => $refused->getMessage()]);
        }

        return $this->back($request, $user)->with('status', 'Commission de '.Money::format($commission->amount).' ajoutée pour '.$commission->ambassador()->first()->user->name.'.');
    }

    /** Retour sur la page d'où vient l'action : la liste des ambassadeurs, sinon la fiche de l'inscrit. */
    private function back(Request $request, User $user): RedirectResponse
    {
        return match ($request->input('from')) {
            'list' => redirect()->to(route('admin.ambassadors.index').'#liste'),
            'commissions' => redirect()->to(route('admin.ambassadors.index').'#commissions'),
            default => redirect()->to(route('admin.users.show', $user).'#ambassadeur'),
        };
    }
}
