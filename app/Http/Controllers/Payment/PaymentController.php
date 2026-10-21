<?php

namespace App\Http\Controllers\Payment;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsappPaymentClick;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use App\Support\Duration;
use App\Support\Gemini\ProofReader;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Money;
use App\Support\PaymentChecks;
use App\Support\PaymentCountry;
use App\Support\PaymentOperator;
use App\Support\Plan;
use App\Support\SiteSettings;
use App\Support\WelcomeFlow;
use App\Support\WhatsApp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Parcours de paiement manuel en 4 étapes :
 * 1. formule, 2. pays et moyen de paiement (« opérateur » dans le code), 3. paiement et capture d'écran, 4. vérification.
 *
 * La capture est lue par l'IA (ProofReader) ; si toutes les règles passent
 * (PaymentChecks), un accès provisoire de 24 heures s'ouvre aussitôt, en attendant
 * la validation de l'administrateur. Sinon, le paiement attend sa vérification.
 */
class PaymentController extends Controller
{
    private const ALREADY_PENDING = 'Nous vérifions déjà un paiement de votre part. Attendez la réponse avant d\'en envoyer un autre.';

    /**
     * Étape 1 : choix de la formule.
     */
    public function plans(Request $request): View|RedirectResponse
    {
        if ($pending = $request->user()->pendingPayment()->first()) {
            return to_route('payment.show', $pending);
        }

        return view('payment.plans', [
            'plans' => Plan::all(),
            'countries' => array_map(fn (PaymentCountry $country) => $country->name, array_values(PaymentCountry::available())),
            'subscription' => $request->user()->activeSubscription,
            'rejected' => $this->lastRejected($request),
            'whatsappUrl' => $this->whatsappUrl($request->user()),
        ]);
    }

    /**
     * Étapes 2 et 3 : pays et moyen de paiement, puis numéro à payer et envoi de la capture.
     * Pays choisi d'avance d'après l'indicatif du téléphone ; s'il n'y a qu'un moyen de paiement
     * dans le pays, il est choisi d'office. Tous les blocs sont rendus (cachés sauf le choix en
     * cours) : avec JavaScript, la page enchaîne sans rechargement (public/js/payment-flow.js).
     */
    public function create(Request $request, string $plan): View|RedirectResponse
    {
        $plan = Plan::find($plan) ?? abort(404);

        if ($pending = $request->user()->pendingPayment()->first()) {
            return to_route('payment.show', $pending);
        }

        // Pays sans paiement Mobile Money sur le site : aucune capture, l'équipe explique sur WhatsApp
        // (puis active l'accès avec « Offrir un accès »). Prix en francs CFA, sans aucune conversion.
        if (! $request->user()->canPayOnline()) {
            return view('payment.abroad', [
                'plan' => $plan,
                'country' => $request->user()->countryName(),
                'whatsappUrl' => $this->abroadWhatsappUrl($request->user(), $plan) !== null
                    ? route('payment.whatsapp', $plan->key)
                    : null,
            ]);
        }

        $countries = PaymentCountry::available();
        $chosen = PaymentCountry::findAvailable($request->query('pays') ?? old('country'));
        // Pays du compte (choisi à l'inscription), sinon celui de l'indicatif du téléphone.
        $fromPhone = PaymentCountry::forPhone($request->user()->phone);
        $guessed = PaymentCountry::findAvailable($request->user()->country) ?? $fromPhone;
        $country = $chosen
            ?? (count($countries) === 1 ? array_values($countries)[0] : null)
            ?? $guessed;
        $operator = $country?->operator($request->query('moyen') ?? $request->query('operateur') ?? old('operator'))
            ?? ($country !== null && count($country->operators) === 1 ? array_values($country->operators)[0] : null);

        return view('payment.create', [
            'plan' => $plan,
            'countries' => $countries,
            'country' => $country,
            // Pays choisi d'après l'indicatif du téléphone (phrase d'explication sous les pays).
            'countryFromPhone' => $chosen === null && count($countries) > 1 && $country !== null,
            'countryMatchesPhone' => $country !== null && $country->code === $fromPhone?->code,
            'operator' => $operator,
            'maxMegabytes' => (int) (config('payment.proofs.max_kilobytes') / 1024),
            'whatsappUrl' => $this->whatsappUrl($request->user(), 'Bonjour, je voudrais payer la formule '.$plan->name.'. Comment faire ?'),
        ]);
    }

    /**
     * Étape 3 : enregistrement et lecture de la capture.
     */
    public function store(StorePaymentRequest $request, string $plan, ProofReader $reader): RedirectResponse
    {
        $plan = Plan::find($plan) ?? abort(404);
        $user = $request->user();

        // Hors des pays de paiement : jamais de capture Mobile Money (rien n'est enregistré).
        if (! $user->canPayOnline()) {
            return to_route('payment.create', $plan->key);
        }

        if ($pending = $user->pendingPayment()->first()) {
            return to_route('payment.show', $pending)->with('status', self::ALREADY_PENDING);
        }

        $limiter = 'payment-proof:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiter, (int) config('payment.proofs.per_hour'))) {
            throw ValidationException::withMessages([
                'proof' => 'Vous avez envoyé beaucoup de captures. Réessayez dans '.Duration::format(RateLimiter::availableIn($limiter) + 59).'.',
            ]);
        }

        RateLimiter::hit($limiter, 3600);

        $country = PaymentCountry::findAvailable($request->validated('country'));
        $operator = $country->operator($request->validated('operator'));
        $file = $request->file('proof');
        $contents = (string) $file->get();
        $hash = hash('sha256', $contents);
        $path = Payment::proofDisk()->putFile(config('payment.proofs.directory').'/'.now()->format('Y/m'), $file);

        // L'IA recopie ce qu'elle lit ; les règles du code décident.
        $analysis = $reader->read($contents, (string) $file->getMimeType());
        $checks = PaymentChecks::evaluate($analysis, $operator, $plan->price, $hash);

        try {
            $payment = $this->createPayment($user, $plan, $country, $operator, $path, $checks, $analysis, $hash);
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés : la base garantit « un seul paiement en attente »,
            // « une image une seule fois » et « un numéro de transaction une seule fois ».
            if ($pending = $user->pendingPayment()->first()) {
                Payment::proofDisk()->delete($path);

                return to_route('payment.show', $pending)->with('status', self::ALREADY_PENDING);
            }

            $payment = $this->createPayment($user, $plan, $country, $operator, $path, $checks->asDuplicate(), $analysis, null);
        }

        if ($payment->checksPassed() && $payment->grantProvisional() !== null) {
            // Accès ouvert : la salutation de bienvenue (son prénom) se prépare après la réponse.
            WelcomeMessage::queueOpening($user);
        }

        // Alerte de l'administrateur (notification et e-mail), envoyée APRÈS la réponse : l'apprenant n'attend rien.
        AdminAlerts::dispatch(AlertBuilder::captured($payment->fresh(['user', 'subscription'])));

        return to_route('payment.show', $payment);
    }

    /**
     * Apprenant d'un pays sans paiement en ligne : « Écrire sur WhatsApp ». Le clic est enregistré ICI, côté
     * serveur, puis l'apprenant est renvoyé aussitôt vers WhatsApp (une seule écriture en base ; l'alerte part
     * après la réponse). Un échec d'enregistrement ou d'alerte ne retient jamais l'apprenant.
     */
    public function whatsapp(Request $request, string $plan): RedirectResponse
    {
        $plan = Plan::find($plan) ?? abort(404);
        $user = $request->user();

        if ($user->canPayOnline()) {
            return to_route('payment.create', $plan->key);
        }

        $url = $this->abroadWhatsappUrl($user, $plan);

        if ($url === null) {
            return to_route('contact');
        }

        try {
            $recent = WhatsappPaymentClick::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subMinutes((int) config('alerts.whatsapp_click_quiet_minutes')))
                ->exists();

            $click = new WhatsappPaymentClick(['plan' => $plan->key]);
            $click->user_id = $user->id;
            $click->save();

            if (! $recent) {
                AdminAlerts::dispatch(AlertBuilder::whatsappClick($user, $plan->name, $plan->formattedPrice()));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->away($url);
    }

    /**
     * Étape 4 : état du paiement (accès provisoire, vérification en cours, validé ou refusé).
     */
    public function show(Request $request, Payment $payment): View|RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        // Premier accès actif (accès provisoire ouvert par la capture) : bienvenue de Teacher Joe, une seule fois.
        if ($welcome = WelcomeFlow::redirect($request->user())) {
            return $welcome;
        }

        $user = $request->user();
        $provisional = $payment->isPending() ? $payment->provisionalSubscription() : null;

        return view('payment.show', [
            'payment' => $payment,
            'provisional' => $provisional,
            'provisionalLeft' => $provisional ? Duration::format((int) now()->diffInSeconds($provisional->provisional_until, true)) : null,
            'hours' => (string) config('platform.contact.hours'),
            'whatsappUrl' => $this->whatsappUrl($user, implode("\n", [
                'Bonjour, j\'ai payé mon abonnement '.config('platform.name').'.',
                'Nom : '.$user->name,
                'Compte : '.$user->phone,
                'Formule : '.$payment->plan_name,
                'Montant : '.Money::format($payment->amount, $payment->currency),
                'Moyen de paiement : '.$payment->operator_name,
                'Envoyé le : '.$payment->created_at->translatedFormat('j F Y à H:i'),
            ])),
        ]);
    }

    private function createPayment(User $user, Plan $plan, PaymentCountry $country, PaymentOperator $operator, string $path, PaymentChecks $checks, ?array $analysis, ?string $hash): Payment
    {
        return $user->payments()->create([
            'plan' => $plan->key,
            'plan_name' => $plan->name,
            'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays,
            'amount' => $plan->price,
            'currency' => config('platform.currency'),
            'country' => $country->code,
            'operator' => $operator->key,
            'operator_name' => $operator->name,
            'recipient_number' => $operator->displayNumber(),
            'proof_path' => $path,
            // Gardés seulement s'ils sont nouveaux (index uniques) : une copie reste repérée par sa règle.
            'proof_hash' => $hash !== null && ! $checks->hashUsed ? $hash : null,
            'transaction_key' => $checks->transactionKeyIfNew(),
            'analysis' => $analysis,
            'checks' => $checks->checks,
        ]);
    }

    /**
     * Lien WhatsApp vers l'équipe (null tant que le numéro n'est pas saisi dans les Réglages).
     */
    private function whatsappUrl(User $user, ?string $message = null): ?string
    {
        $number = SiteSettings::whatsapp();

        return $number === null ? null : WhatsApp::url($number, $message ?? implode("\n", [
            'Bonjour, j\'ai une question sur mon paiement '.config('platform.name').'.',
            'Compte : '.$user->phone,
        ]));
    }

    /**
     * Pays sans paiement en ligne : message WhatsApp prérempli (nom, pays, formule choisie).
     */
    private function abroadWhatsappUrl(User $user, Plan $plan): ?string
    {
        $number = SiteSettings::whatsapp();

        return $number === null ? null : WhatsApp::url($number, implode("\n", [
            'Bonjour, je voudrais m\'abonner à '.config('platform.name').'.',
            'Nom : '.$user->name,
            'Pays : '.$user->countryName(),
            'Formule choisie : '.$plan->name.' ('.$plan->formattedPrice().')',
            'Compte : '.$user->phone,
        ]));
    }

    private function lastRejected(Request $request): ?Payment
    {
        $latest = $request->user()->latestPayment()->first();

        return $latest?->status === PaymentStatus::Rejected ? $latest : null;
    }
}
