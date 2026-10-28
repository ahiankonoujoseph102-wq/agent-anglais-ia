<?php

namespace App\Support\Admin;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Greeting;
use App\Support\Origins;
use App\Support\Plan;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Page « Abonnés payants » de l'administration : abonnements des apprenants, séparés en trois groupes pour ne
 * pas fausser les chiffres (payants ; mois gratuits des ambassadeurs ; autres accès offerts), filtre
 * actifs / expirés / tous, tri par date de fin et message de relance WhatsApp déjà rédigé.
 *
 * Actif = comme User::activeSubscription : formule active, ou accès provisoire encore valable, dont la fin
 * n'est pas passée (une formule déjà payée qui commence plus tard compte aussi). Un abonnement refusé
 * (annulé) ou jamais ouvert n'est jamais montré : ce n'est pas un abonné.
 */
final class Subscribers
{
    public const FILTERS = ['actifs' => 'Actifs', 'expires' => 'Expirés', 'tous' => 'Tous'];

    public const SORTS = ['fin' => 'Fin la plus proche d\'abord', 'fin-recente' => 'Fin la plus lointaine d\'abord'];

    public const GROUPS = [
        'paid' => 'Abonnés payants',
        'ambassador' => 'Ambassadeurs (mois gratuit)',
        'granted' => 'Accès offerts (démonstrations, membres fondateurs…)',
    ];

    /** Payant, mois gratuit d'un ambassadeur, ou autre accès offert par l'équipe. */
    public static function group(Subscription $subscription): string
    {
        return match (true) {
            $subscription->ambassador_id !== null => 'ambassador',
            $subscription->granted_days !== null => 'granted',
            default => 'paid',
        };
    }

    /**
     * Abonnements à montrer, déjà triés par date de fin, rangés par groupe.
     *
     * @return array<string, Collection<int, Subscription>>
     */
    public static function list(string $filter = 'actifs', string $sort = 'fin'): array
    {
        $now = now();
        $subscriptions = Subscription::query()
            ->whereHas('user', fn (Builder $query) => $query->where('role', Role::Learner))
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Provisional, SubscriptionStatus::Suspended, SubscriptionStatus::Expired])
            ->when($filter === 'actifs', fn (Builder $query) => self::whereRunning($query, $now))
            ->when($filter === 'expires', fn (Builder $query) => $query->whereNot(fn (Builder $query) => self::whereRunning($query, $now)))
            ->with(['user.origin', 'payments'])
            ->orderBy('ends_at', $sort === 'fin-recente' ? 'desc' : 'asc')
            ->orderBy('id')
            ->get();

        // Une liste distincte par groupe (jamais la même collection partagée).
        $groups = array_map(fn () => collect(), self::GROUPS);

        foreach ($subscriptions as $subscription) {
            $groups[self::group($subscription)]->push($subscription);
        }

        return $groups;
    }

    /**
     * Chiffres du haut de la page : abonnés payants actifs (au total, par formule, dont en vérification),
     * ambassadeurs et accès offerts actifs (à part), somme encaissée ce mois-ci.
     *
     * @return array{paid: int, by_plan: array<string, array{name: string, count: int}>, provisional: int, ambassador: int, granted: int, month_total: int}
     */
    public static function totals(): array
    {
        $groups = self::list('actifs');
        $now = now();
        // Une formule déjà payée qui commence plus tard ne compte pas deux fois la même personne.
        $current = fn (Collection $rows) => $rows->filter(fn (Subscription $s) => $s->starts_at->lte($now))->unique('user_id');
        $paid = $current($groups['paid']);
        $byPlan = [];

        foreach (Plan::all() as $plan) {
            $byPlan[$plan->key] = ['name' => $plan->name, 'count' => $paid->where('plan', $plan->key)->count()];
        }

        return [
            'paid' => $paid->count(),
            'by_plan' => $byPlan,
            'provisional' => $paid->filter(fn (Subscription $s) => $s->status === SubscriptionStatus::Provisional)->count(),
            'ambassador' => $current($groups['ambassador'])->count(),
            'granted' => $current($groups['granted'])->count(),
            // Même calcul que « Historique » : paiements validés pendant le mois en cours.
            'month_total' => (int) Payment::query()
                ->where('status', PaymentStatus::Validated)
                ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private static function whereRunning(Builder $query, Carbon $now): void
    {
        $query->where('ends_at', '>', $now)
            ->where(fn (Builder $query) => $query
                ->where('status', SubscriptionStatus::Active)
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', SubscriptionStatus::Provisional)
                    ->where('provisional_until', '>', $now)));
    }

    public static function isRunning(Subscription $subscription): bool
    {
        return $subscription->ends_at->isFuture()
            && ($subscription->status === SubscriptionStatus::Active
                || ($subscription->status === SubscriptionStatus::Provisional && $subscription->provisional_until?->isFuture()));
    }

    /** Prénom (premier mot du nom) et nom (le reste), comme le nom a été saisi à l'inscription. */
    public static function names(string $name): array
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];

        return [Greeting::firstName($name), implode(' ', array_slice($words, 1))];
    }

    /** Jours restants jusqu'à la fin (0 : se termine aujourd'hui ; null : terminé). */
    public static function daysLeft(Subscription $subscription): ?int
    {
        if (! self::isRunning($subscription)) {
            return null;
        }

        return (int) today()->diffInDays($subscription->ends_at->copy()->startOfDay());
    }

    /**
     * Montant payé : paiements validés de cet abonnement ; sinon le paiement en vérification (accès provisoire).
     *
     * @return array{amount: int, pending: bool}|null null : accès gratuit
     */
    public static function paid(Subscription $subscription): ?array
    {
        if (self::group($subscription) !== 'paid') {
            return null;
        }

        $validated = $subscription->payments->where('status', PaymentStatus::Validated);

        if ($validated->isNotEmpty()) {
            return ['amount' => (int) $validated->sum('amount'), 'pending' => false];
        }

        $pending = $subscription->payments->firstWhere('status', PaymentStatus::Pending);

        return ['amount' => (int) ($pending?->amount ?? $subscription->price), 'pending' => $pending !== null];
    }

    public static function origin(Subscription $subscription): string
    {
        $channel = $subscription->user->origin?->channel;

        return $channel === null ? 'Inconnue' : Origins::label($channel);
    }

    /** La fin est assez proche pour une relance (même délai que le rappel de « Mon espace »). */
    public static function endsSoon(Subscription $subscription): bool
    {
        $days = self::daysLeft($subscription);

        return $days !== null && $days < (int) config('payment.renewal_reminder_days');
    }

    /**
     * Lien WhatsApp vers l'apprenant, avec un message déjà rédigé (uniquement des faits : formule, date de fin,
     * où renouveler). Relance pour une fin proche ou passée ; simple salutation sinon.
     */
    public static function whatsappUrl(Subscription $subscription): string
    {
        return WhatsApp::url($subscription->user->phone, self::message($subscription));
    }

    public static function message(Subscription $subscription): string
    {
        [$firstName] = self::names($subscription->user->name);
        $hello = 'Bonjour'.($firstName !== '' ? ' '.$firstName : '').", c'est l'équipe de ".config('platform.name').'.';
        $teacher = config('avatar.model.name');
        $plans = route('payment.plans');
        $date = $subscription->ends_at->translatedFormat('j F Y');
        $group = self::group($subscription);
        $days = self::daysLeft($subscription);
        $when = match ($days) {
            0 => "aujourd'hui",
            1 => 'demain',
            default => 'le '.$date,
        };
        $what = $group === 'paid' ? 'Votre formule '.$subscription->plan_name : 'Votre accès offert ('.$subscription->plan_name.')';
        $question = 'Si vous avez une question, répondez simplement à ce message.';

        if ($group === 'ambassador') {
            return $hello;
        }

        if ($days === null) {
            $ended = $group === 'paid' ? 'terminée' : 'terminé';

            return "{$hello} {$what} s'est {$ended} le {$date}. Pour reprendre vos séances avec {$teacher}, choisissez une formule ici : {$plans} {$question}";
        }

        if (self::endsSoon($subscription)) {
            $action = $group === 'paid'
                ? "Pour continuer vos séances avec {$teacher} sans interruption, vous pouvez la renouveler dès maintenant ici : {$plans}"
                : "Pour continuer ensuite vos séances avec {$teacher}, vous pouvez choisir une formule ici : {$plans}";

            return "{$hello} {$what} se termine {$when}. {$action} {$question}";
        }

        return $hello;
    }
}
