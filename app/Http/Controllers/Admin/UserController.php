<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfferAccessRequest;
use App\Models\Subscription;
use App\Models\User;
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
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'total' => User::count(),
        ]);
    }

    /**
     * Fiche d'un inscrit : évaluation, abonnements, paiements, mot de passe.
     */
    public function show(Request $request, User $user): View
    {
        $user->load(['assessment', 'activeSubscription']);

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
            'temporaryPassword' => $temporaryPassword,
            'whatsappUrl' => $whatsappUrl,
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
}
