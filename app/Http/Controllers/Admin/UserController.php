<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfferAccessRequest;
use App\Models\Ambassador;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Countries;
use App\Support\Live\Transcripts;
use App\Support\PhoneNumber;
use App\Support\Plan;
use App\Support\TemporaryPassword;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        // Filtre par pays : un pays de paiement, ou « autres » (tous les pays sans paiement Mobile Money, inconnus compris).
        $paymentCountries = array_keys((array) config('payment.countries'));
        $country = (string) $request->query('pays');
        $country = $country === 'autres' || in_array($country, $paymentCountries, true) ? $country : '';

        $users = User::query()
            ->with('activeSubscription')
            ->when($search !== '', function (Builder $query) use ($search) {
                $digits = preg_replace('/\D+/', '', PhoneNumber::normalize($search) ?? $search);

                $query->where(function (Builder $query) use ($search, $digits) {
                    $query->where('name', 'like', '%'.$search.'%');

                    if (strlen($digits) >= 3) {
                        $query->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->when($country === 'autres', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('country')->orWhereNotIn('country', $paymentCountries)))
            ->when($country !== '' && $country !== 'autres', fn (Builder $query) => $query->where('country', $country))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'country' => $country,
            'paymentCountries' => array_combine($paymentCountries, array_map(fn (string $code) => Countries::name($code), $paymentCountries)),
            'total' => User::count(),
        ]);
    }

    /**
     * Fiche d'un inscrit : évaluation, abonnements, paiements, mot de passe.
     */
    public function show(Request $request, User $user): View
    {
        $user->load(['assessment', 'activeSubscription', 'origin', 'ambassador', 'referral.ambassador.user', 'referralCommission']);

        $temporaryPassword = $request->session()->get('temporary_password');
        $whatsappUrl = null;

        if ($temporaryPassword !== null) {
            $whatsappUrl = WhatsApp::url($user->phone, implode("\n", [
                'Bonjour '.$user->name.',',
                'Voici votre mot de passe provisoire '.config('platform.name').' : '.$temporaryPassword,
                'Connectez-vous avec votre numéro '.$user->phone.' : il vous sera demandé d\'en choisir un nouveau.',
            ]));
        }

        $grants = $user->subscriptions()->granted()->with('grantedBy')->latest('id')->get();

        return view('admin.users.show', [
            'user' => $user,
            'plans' => Plan::all(),
            'grants' => $grants,
            // L'accès offert en cours, s'il y en a un ; sinon la raison qui empêche d'en offrir un.
            'currentGrant' => $grants->first(fn (Subscription $grant) => $grant->status === SubscriptionStatus::Active && $grant->ends_at->isFuture()),
            'grantBlocked' => OfferAccessRequest::blockingMessage($user),
            'subscriptions' => $user->subscriptions()->latest('ends_at')->get(),
            'payments' => $user->payments()->with(['user', 'subscription'])->latest('id')->get(),
            'courseLessons' => $user->courseLessons()->with('pages')->latest('id')->get(),
            'conversations' => Transcripts::sessionsOf($user),
            'temporaryPassword' => $temporaryPassword,
            'whatsappUrl' => $whatsappUrl,
            'ambassadorGrant' => $user->ambassador?->currentGrant(),
            // Inscrits de cet ambassadeur, et ambassadeurs actifs pour corriger un rattachement.
            'ambassadorReferrals' => $user->ambassador?->referrals()->with('user:id,name')->latest()->get() ?? collect(),
            'ambassadorCommissions' => $user->ambassador?->commissions()->with('user:id,name')->latest()->get() ?? collect(),
            'activeAmbassadors' => Ambassador::query()->active()->with('user:id,name')->where('user_id', '!=', $user->id)->get(),
            'freePlaces' => AmbassadorProgram::freePlaces(),
            'places' => AmbassadorProgram::places(),
        ]);
    }

    /**
     * Génère un mot de passe provisoire, affiché une seule fois à l'administrateur.
     * L'apprenant devra en choisir un nouveau à sa prochaine connexion.
     */
    public function temporaryPassword(User $user): RedirectResponse
    {
        $password = TemporaryPassword::generate();

        $user->password = $password;
        $user->must_change_password = true;
        $user->setRememberToken(null);
        $user->save();

        // Déconnecte les sessions ouvertes de ce compte.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        // Donnée « flash » : disparaît après le prochain affichage de la fiche.
        return redirect()->to(route('admin.users.show', $user).'#mot-de-passe-provisoire')->with('temporary_password', $password);
    }

    /**
     * Case « Compte de test » : un compte de test passe la nouvelle évaluation quand l'interrupteur des Réglages vaut
     * « Comptes de test seulement » (App\Support\Live\AssessmentVersion).
     */
    public function testAccount(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['is_test_account' => $request->boolean('is_test_account')])->save();

        return redirect()->to(route('admin.users.show', $user).'#compte-de-test')->with('status', $user->is_test_account
            ? 'Ce compte est maintenant un compte de test.'
            : 'Ce compte n\'est plus un compte de test.');
    }
}
