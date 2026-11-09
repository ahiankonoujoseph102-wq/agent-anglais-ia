<?php

namespace App\Support\Reminders;

use App\Enums\Goal;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Mail\FollowUpMail;
use App\Models\LearnerMail;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Duration;
use App\Support\LearnerName;
use App\Support\MailQuota;
use App\Support\Money;
use App\Support\Plan;
use App\Support\Redact;
use App\Support\Reviews\GoogleReviews;
use App\Support\Reviews\ReviewInvitations;
use App\Support\Teachers\Teachers;
use App\Support\Trial;
use App\Support\Videos\SiteVideos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Relances des inscrits qui n'ont pas encore de formule payée : un e-mail par étape (inscrit sans évaluation,
 * évaluation sans essai, essai terminé sans formule, puis deux relances espacées après l'essai) et le lancement de
 * l'offre (learners:offer-trial) ; et des anciens abonnés payants dont la formule est finie sans renouvellement
 * (trois relances espacées, puis plus rien). Les relances « commerciales » (LearnerMail::SALES) partent par petits
 * lots : platform.follow_ups.sales.per_day par jour d'envoi, un jour sur every_days, jamais chaque jour. Chaque
 * campagne part UNE fois par compte, jamais deux e-mails de relance à moins de platform.follow_ups.gap_hours heures,
 * jamais à qui a demandé à ne plus en recevoir. Textes écrits par le code (aucun modèle d'intelligence artificielle).
 * Sans adresse e-mail : noté « no_email », l'équipe relance sur WhatsApp depuis « Relances » de l'administration.
 */
final class FollowUps
{
    public const STAGE_NO_ASSESSMENT = 'no_assessment';

    public const STAGE_NO_TRIAL = 'no_trial';

    public const STAGE_ON_TRIAL = 'on_trial';

    public const STAGE_TRIAL_ENDED = 'trial_ended';

    /** Accès offert en cours, ou essai déjà utilisé sans relance utile : rien d'automatique. */
    public const STAGE_OTHER = 'other';

    public const STAGE_LABELS = [
        self::STAGE_NO_ASSESSMENT => 'Inscrit, sans évaluation',
        self::STAGE_NO_TRIAL => 'Évaluation faite, essai pas commencé',
        self::STAGE_ON_TRIAL => 'Essai gratuit en cours',
        self::STAGE_TRIAL_ENDED => 'Essai terminé, sans formule',
        self::STAGE_OTHER => 'Autre (accès offert…)',
    ];

    /**
     * Inscrits sans formule payée : apprenants qui n'ont jamais eu d'abonnement payé (même provisoire ou terminé ;
     * un paiement refusé ne compte pas).
     *
     * @return Builder<User>
     */
    public static function prospects(): Builder
    {
        return User::query()
            ->where('role', Role::Learner->value)
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->whereNull('granted_days')
                ->where('status', '!=', SubscriptionStatus::Cancelled->value));
    }

    public static function stage(User $user): string
    {
        $subscription = $user->activeSubscription;

        if ($subscription?->isTrial()) {
            return self::STAGE_ON_TRIAL;
        }

        if ($subscription !== null) {
            return self::STAGE_OTHER;
        }

        if (Trial::latest($user) !== null) {
            return self::STAGE_TRIAL_ENDED;
        }

        if ($user->assessment()->whereNotNull('level')->doesntExist()) {
            return self::STAGE_NO_ASSESSMENT;
        }

        return Trial::eligible($user) ? self::STAGE_NO_TRIAL : self::STAGE_OTHER;
    }

    public static function enabled(): bool
    {
        return (bool) config('platform.follow_ups.enabled');
    }

    /** Une passe : prépare les relances dues, puis envoie un petit lot (learners:follow-up, chaque heure). */
    public static function run(): array
    {
        if (! self::enabled()) {
            return ['queued' => 0, 'sent' => 0, 'no_email' => 0, 'skipped' => 0, 'failed' => 0];
        }

        return ['queued' => self::queueDue()] + self::sendPending();
    }

    /** Relances dues d'après l'étape de chaque inscrit récent (une par campagne et par compte). */
    public static function queueDue(): int
    {
        $config = (array) config('platform.follow_ups');
        $queued = 0;

        self::prospects()
            ->whereNull('follow_up_opt_out_at')
            ->where('created_at', '>=', now()->subDays((int) $config['max_age_days']))
            ->with(['assessment', 'learnerMails'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($config, &$queued) {
                foreach ($users as $user) {
                    $campaign = self::dueCampaign($user, $config);

                    // Déjà prévenu par le lancement de l'offre : pas de second e-mail « évaluation » (l'essai, oui).
                    if ($campaign === LearnerMail::NO_ASSESSMENT && $user->learnerMails->where('campaign', LearnerMail::OFFER)->isNotEmpty()) {
                        continue;
                    }

                    if ($campaign !== null && $user->learnerMails->where('campaign', $campaign)->isEmpty()) {
                        $user->learnerMails()->firstOrCreate(['campaign' => $campaign], ['status' => LearnerMail::PENDING]);
                        $queued++;
                    }
                }
            });

        return $queued + self::queueLapsed() + ReviewInvitations::queue();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function dueCampaign(User $user, array $config): ?string
    {
        $fresh = User::query()->find($user->id);

        if (self::stage($fresh) === self::STAGE_TRIAL_ENDED) {
            $ended = Trial::latest($fresh)?->ends_at;
            [$second, $third] = array_map('intval', (array) $config['sales']['trial_ended_days']);

            return $ended === null ? null : self::nextInSequence($user, [
                LearnerMail::TRIAL_ENDED => $ended->lte(now()->subHours((int) $config['trial_ended_after_hours'])),
                LearnerMail::TRIAL_ENDED_2 => $ended->lte(now()->subDays($second)),
                LearnerMail::TRIAL_ENDED_3 => $ended->lte(now()->subDays($third)),
            ]);
        }

        return match (self::stage($fresh)) {
            self::STAGE_NO_ASSESSMENT => $user->created_at->lte(now()->subHours((int) $config['no_assessment_after_hours'])) ? LearnerMail::NO_ASSESSMENT : null,
            self::STAGE_NO_TRIAL => ($user->assessment?->ended_at ?? $user->assessment?->created_at)?->lte(now()->subHours((int) $config['no_trial_after_hours'])) ? LearnerMail::NO_TRIAL : null,
            default => null,
        };
    }

    /**
     * Prochaine relance d'une suite : la première pas encore préparée, si son délai est passé et que les précédentes
     * l'ont été (jamais la troisième sans la deuxième).
     *
     * @param  array<string, bool>  $steps  campagne => délai passé
     */
    private static function nextInSequence(User $user, array $steps): ?string
    {
        foreach ($steps as $campaign => $due) {
            if ($user->learnerMails->where('campaign', $campaign)->isEmpty()) {
                return $due ? $campaign : null;
            }
        }

        return null;
    }

    /**
     * Ancien abonné payant : dernière formule payée (ni accès offert, ni mois d'ambassadeur, ni formule refusée), finie,
     * sans accès en cours ni paiement en vérification ; null sinon (jamais l'équipe).
     */
    public static function lapsedSubscription(User $user): ?Subscription
    {
        if ($user->role !== Role::Learner || $user->activeSubscription()->exists()
            || $user->payments()->where('status', PaymentStatus::Pending)->exists()) {
            return null;
        }

        $last = $user->subscriptions()->paid()->whereNull('ambassador_id')
            ->where('status', '!=', SubscriptionStatus::Cancelled->value)
            ->latest('ends_at')->first();

        return $last !== null && $last->ends_at->isPast() ? $last : null;
    }

    /** Relances dues des anciens abonnés (trois, espacées, après le bilan de fin de formule). */
    private static function queueLapsed(): int
    {
        $config = (array) config('platform.follow_ups.sales');
        $days = array_map('intval', (array) $config['lapsed_days']);
        $maxDays = (int) $config['lapsed_max_days'];
        $queued = 0;

        User::query()
            ->where('role', Role::Learner->value)
            ->whereNull('follow_up_opt_out_at')
            ->whereHas('subscriptions', fn ($query) => $query->paid()->whereNull('ambassador_id')
                ->where('status', '!=', SubscriptionStatus::Cancelled->value)
                ->where('ends_at', '<=', now())
                ->where('ends_at', '>', now()->subDays($maxDays)))
            ->with('learnerMails')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($days, $maxDays, &$queued) {
                foreach ($users as $user) {
                    $last = self::lapsedSubscription($user);

                    if ($last === null || $last->ends_at->lt(now()->subDays($maxDays))) {
                        continue;
                    }

                    // Une suite d'une formule plus ancienne (l'élève avait repris puis arrêté de nouveau) : on recommence.
                    $old = $user->learnerMails->whereIn('campaign', LearnerMail::LAPSED)
                        ->filter(fn (LearnerMail $mail) => $mail->created_at->lt($last->starts_at));

                    if ($old->isNotEmpty()) {
                        LearnerMail::query()->whereKey($old->modelKeys())->delete();
                        $user->load('learnerMails');
                    }

                    $campaign = self::nextInSequence($user, [
                        LearnerMail::LAPSED_1 => $last->ends_at->lte(now()->subDays($days[0])),
                        LearnerMail::LAPSED_2 => $last->ends_at->lte(now()->subDays($days[1])),
                        LearnerMail::LAPSED_3 => $last->ends_at->lte(now()->subDays($days[2])),
                    ]);

                    if ($campaign !== null) {
                        $user->learnerMails()->firstOrCreate(['campaign' => $campaign], ['status' => LearnerMail::PENDING]);
                        $queued++;
                    }
                }
            });

        return $queued;
    }

    /** Relances commerciales parties aujourd'hui, réussies ou non. */
    public static function salesSentToday(): int
    {
        return LearnerMail::query()
            ->whereIn('campaign', LearnerMail::SALES)
            ->whereIn('status', [LearnerMail::SENT, LearnerMail::FAILED])
            ->where('sent_at', '>=', today())
            ->count();
    }

    /**
     * Jour d'envoi des relances commerciales ? Jamais chaque jour : un jour sur every_days (le jour même d'un envoi
     * déjà commencé compte), au plus per_day e-mails ce jour-là.
     */
    public static function salesDayOpen(): bool
    {
        $config = (array) config('platform.follow_ups.sales');
        $today = self::salesSentToday();

        if ($today >= (int) $config['per_day']) {
            return false;
        }

        if ($today > 0) {
            return true;
        }

        $last = LearnerMail::query()
            ->whereIn('campaign', LearnerMail::SALES)
            ->whereIn('status', [LearnerMail::SENT, LearnerMail::FAILED])
            ->where('sent_at', '<', today())
            ->max('sent_at');

        return $last === null
            || CarbonImmutable::parse($last)->startOfDay()->lte(today()->subDays(max(1, (int) $config['every_days'])));
    }

    /** E-mails de relance partis aujourd'hui, réussis ou non. */
    public static function sentToday(): int
    {
        return LearnerMail::query()
            ->whereIn('campaign', LearnerMail::CAMPAIGNS)
            ->whereIn('status', [LearnerMail::SENT, LearnerMail::FAILED])
            ->where('sent_at', '>=', today())
            ->count();
    }

    /** Invitations à laisser un avis parties aujourd'hui. */
    public static function reviewsSentToday(): int
    {
        return LearnerMail::query()
            ->whereIn('campaign', LearnerMail::REVIEWS)
            ->whereIn('status', [LearnerMail::SENT, LearnerMail::FAILED])
            ->where('sent_at', '>=', today())
            ->count();
    }

    public static function allowsMail(): bool
    {
        return self::sentToday() < (int) config('platform.follow_ups.daily_emails')
            && MailQuota::usedToday() < MailQuota::limit() - (int) config('platform.follow_ups.mail_reserve');
    }

    /**
     * Envoie un petit lot d'e-mails en attente (le plus ancien d'abord).
     *
     * @return array{sent: int, no_email: int, skipped: int, failed: int}
     */
    public static function sendPending(): array
    {
        $result = ['sent' => 0, 'no_email' => 0, 'skipped' => 0, 'failed' => 0];

        if (! MailQuota::configured()) {
            return $result;
        }

        $batch = (int) config('platform.follow_ups.batch');
        $regular = LearnerMail::query()
            ->where('status', LearnerMail::PENDING)
            ->whereIn('campaign', array_values(array_diff(LearnerMail::CAMPAIGNS, LearnerMail::SALES, LearnerMail::REVIEWS)))
            ->orderBy('id')
            ->limit($batch * 3)
            ->get();
        $sent = 0;

        foreach ($regular as $mail) {
            if ($sent >= $batch || ! self::allowsMail()) {
                break;
            }

            $sent += self::sendOne($mail, $result);
        }

        // Invitations à laisser un avis : leur propre petit lot par jour, les plus récentes expériences d'abord.
        $reviews = LearnerMail::query()
            ->where('status', LearnerMail::PENDING)
            ->whereIn('campaign', LearnerMail::REVIEWS)
            ->orderByDesc('id')
            ->limit($batch * 3)
            ->get();
        $reviewsSent = 0;

        foreach ($reviews as $mail) {
            if ($reviewsSent >= $batch || self::reviewsSentToday() >= (int) config('platform.reviews.emails_per_day') || ! self::allowsMail()) {
                break;
            }

            $reviewsSent += self::sendOne($mail, $result);
        }

        // Relances commerciales : les plus anciennes d'abord, un petit lot les jours d'envoi seulement.
        if (! self::salesDayOpen()) {
            return $result;
        }

        $sales = LearnerMail::query()
            ->where('status', LearnerMail::PENDING)
            ->whereIn('campaign', LearnerMail::SALES)
            ->orderBy('id')
            ->limit((int) config('platform.follow_ups.sales.per_day') * 3)
            ->get();

        foreach ($sales as $mail) {
            if (! self::salesDayOpen() || ! self::allowsMail()) {
                break;
            }

            self::sendOne($mail, $result);
        }

        return $result;
    }

    /**
     * @param  array{sent: int, no_email: int, skipped: int, failed: int}  $result
     * @return int 1 si un e-mail est parti (réussi ou non)
     */
    private static function sendOne(LearnerMail $mail, array &$result): int
    {
        $user = User::query()->find($mail->user_id);
        $outcome = $user === null ? LearnerMail::SKIPPED : self::deliver($mail, $user);

        if ($outcome === null) {
            return 0;
        }

        $result[$outcome === LearnerMail::NO_EMAIL ? 'no_email' : $outcome]++;

        return $outcome === LearnerMail::SENT || $outcome === LearnerMail::FAILED ? 1 : 0;
    }

    /** Envoie un e-mail ; renvoie son nouvel état, ou null pour le garder en attente (une autre relance trop récente). */
    private static function deliver(LearnerMail $mail, User $user): ?string
    {
        $content = self::content($mail->campaign, $user);

        $targeted = match (true) {
            in_array($mail->campaign, LearnerMail::LAPSED, true) => self::lapsedSubscription($user) !== null,
            in_array($mail->campaign, LearnerMail::REVIEWS, true) => ReviewInvitations::wanted($user),
            $mail->campaign === LearnerMail::GOOGLE_REVIEW => $user->role === Role::Learner,
            default => self::prospects()->whereKey($user->id)->exists(),
        };

        if ($content === null || $user->follow_up_opt_out_at !== null || ! $targeted) {
            $mail->forceFill(['status' => LearnerMail::SKIPPED])->save();

            return LearnerMail::SKIPPED;
        }

        if (blank($user->email)) {
            $mail->forceFill(['status' => LearnerMail::NO_EMAIL])->save();

            return LearnerMail::NO_EMAIL;
        }

        $recent = $user->learnerMails()
            ->whereKeyNot($mail->id)
            ->whereIn('campaign', LearnerMail::CAMPAIGNS)
            ->where('status', LearnerMail::SENT)
            ->where('sent_at', '>', now()->subHours((int) config('platform.follow_ups.gap_hours')))
            ->exists();

        if ($recent) {
            return null;
        }

        try {
            $content['label'] = LearnerMail::CAMPAIGN_LABELS[$mail->campaign] ?? $mail->campaign;
            Mail::to($user->email)->send(new FollowUpMail($user, $content, self::stopUrl($user)));
            $mail->forceFill(['status' => LearnerMail::SENT, 'sent_at' => now(), 'error' => null])->save();

            return LearnerMail::SENT;
        } catch (Throwable $e) {
            report($e);
            $mail->forceFill(['status' => LearnerMail::FAILED, 'sent_at' => now(), 'error' => Redact::reason($e->getMessage())])->save();

            return LearnerMail::FAILED;
        }
    }

    /** Lien « Ne plus recevoir ces e-mails » (signé, sans date de fin, sans connexion). */
    public static function stopUrl(User $user): string
    {
        return URL::signedRoute('follow-ups.stop', ['user' => $user->id]);
    }

    /**
     * Texte d'un e-mail de relance d'après l'étape ACTUELLE de l'inscrit (null : plus rien d'utile à lui dire).
     * Le lancement de l'offre (« offer ») s'adapte : évaluation à passer, ou essai à commencer.
     *
     * @return array{subject: string, title: string, lines: list<string>, button: string, url: string, review: ?string}|null
     */
    public static function content(string $campaign, User $user): ?array
    {
        if (in_array($campaign, LearnerMail::REVIEWS, true)) {
            return ReviewInvitations::content($campaign, $user);
        }

        if ($campaign === LearnerMail::GOOGLE_REVIEW) {
            return GoogleReviews::content($user);
        }

        $stage = self::stage($user);
        $teachers = Teachers::names();
        $minutes = Trial::minutes();
        $assessmentMinutes = (int) config('platform.assessment.max_minutes');
        $platform = (string) config('platform.name');
        $cheapest = collect(Plan::all())->sortBy('price')->first();
        $from = $cheapest ? 'Les formules commencent à '.Money::format($cheapest->price).' ('.$cheapest->name.'), payées depuis votre téléphone par Mobile Money ; rien n\'est prélevé automatiquement.' : '';

        $evaluation = [
            'title' => 'Votre évaluation gratuite vous attend',
            'lines' => [
                "Parlez {$assessmentMinutes} minutes à l'oral avec {$teachers} : vous connaîtrez votre niveau d'anglais, de A1 à C2, avec une mini-leçon sur vos propres erreurs.",
                Trial::enabled() ? "Ensuite, nous vous offrons {$minutes} minutes de vrai cours pour tout découvrir : votre programme personnel, la leçon au tableau, la pratique à l'oral et l'aide à vos cours d'anglais de l'école (« Mes cours »)." : 'Ensuite, vous choisissez votre formule.',
                'Il vous suffit d\'un téléphone, d\'un endroit calme et de quelques minutes.',
            ],
            'button' => 'Passer mon évaluation gratuite',
            'url' => route('assessment.show'),
        ];

        $trial = [
            'title' => "Vos {$minutes} minutes de cours offertes vous attendent",
            'lines' => array_values(array_filter([
                $user->level ? 'Votre niveau : '.$user->level->value.' ('.$user->level->label().').' : null,
                "Découvrez un vrai cours avant de choisir : répondez à 4 petites questions, votre programme personnel est construit, puis {$teachers} vous enseigne la leçon du jour au tableau, vous fait pratiquer et vous fait parler.",
                'Vous pouvez aussi faire expliquer une leçon ou un exercice de votre cours d\'anglais de l\'école, en photo ou en PDF (« Mes cours »).',
                'C\'est gratuit, une seule fois par compte, à utiliser dans les '.Trial::validity().' après l\'avoir commencé.',
            ])),
            'button' => 'Commencer mon essai gratuit',
            'url' => route('dashboard'),
        ];

        $body = match ($campaign) {
            LearnerMail::OFFER => match ($stage) {
                self::STAGE_NO_ASSESSMENT => ['subject' => "Nouveau sur {$platform} : évaluation gratuite, puis {$minutes} minutes de cours offertes"] + $evaluation,
                self::STAGE_NO_TRIAL => ['subject' => "Nouveau sur {$platform} : {$minutes} minutes de cours offertes"] + $trial,
                default => null,
            },
            LearnerMail::NO_ASSESSMENT => $stage === self::STAGE_NO_ASSESSMENT ? ['subject' => 'Votre évaluation orale gratuite vous attend'] + $evaluation : null,
            LearnerMail::NO_TRIAL => $stage === self::STAGE_NO_TRIAL ? ['subject' => "Vos {$minutes} minutes de cours offertes vous attendent"] + $trial : null,
            LearnerMail::TRIAL_ENDED => $stage === self::STAGE_TRIAL_ENDED ? self::trialEnded($user, $from) : null,
            LearnerMail::TRIAL_ENDED_2, LearnerMail::TRIAL_ENDED_3 => $stage === self::STAGE_TRIAL_ENDED ? self::afterTrial($campaign, $user, $from) : null,
            LearnerMail::LAPSED_1, LearnerMail::LAPSED_2, LearnerMail::LAPSED_3 => self::lapsed($campaign, $user),
            default => null,
        };

        if ($body === null) {
            return null;
        }

        if ($campaign === LearnerMail::OFFER) {
            array_unshift($body['lines'], "{$platform} s'est beaucoup amélioré : vous pouvez maintenant choisir votre professeur, {$teachers}, et chaque séance suit une vraie méthode (leçon, pratique, conversation, bilan).");
        }

        return $body + ['review' => null, 'links' => []];
    }

    /**
     * @return array{subject: string, title: string, lines: list<string>, button: string, url: string, review: ?string}
     */
    private static function trialEnded(User $user, string $from): array
    {
        $trial = Trial::latest($user);
        $report = SubscriptionReport::for($trial);
        $lines = $report->worked()
            ? array_values(array_filter($report->lines(), fn (string $line) => ! str_starts_with($line, 'Dans votre programme')))
            : ['Vous n\'avez pas eu le temps d\'utiliser votre séance d\'essai : votre niveau et votre programme vous attendent toujours.'];
        $lines[] = 'Pour continuer chaque jour, choisissez une formule. '.$from;

        return [
            'subject' => 'Votre essai est terminé : on continue ensemble ?',
            'title' => 'Votre séance d\'essai est terminée',
            'lines' => $lines,
            'button' => 'Choisir ma formule',
            'url' => route('payment.plans'),
            'review' => $report->reviewInvitation(),
        ];
    }

    /**
     * Deuxième et dernière relance après l'essai : pourquoi continuer, d'après l'objectif de la personne, puis les formules.
     *
     * @return array<string, mixed>
     */
    private static function afterTrial(string $campaign, User $user, string $from): array
    {
        $teacher = Teachers::forUser($user)->name();
        $level = $user->level ? ' (niveau '.$user->level->value.', '.$user->level->label().')' : '';

        if ($campaign === LearnerMail::TRIAL_ENDED_2) {
            return [
                'subject' => "Votre anglais, la suite avec {$teacher} ?",
                'title' => 'Pourquoi continuer maintenant',
                'lines' => [
                    "Il y a quelques jours, vous avez découvert un vrai cours avec {$teacher}{$level}.",
                    self::goalLine($user),
                    self::regularityLine($teacher),
                    $from,
                ],
                'button' => 'Choisir ma formule',
                'url' => route('payment.plans'),
                'review' => null,
                'links' => self::promoLinks(),
            ];
        }

        return [
            'subject' => "Un dernier message de {$teacher}",
            'title' => 'Votre place vous attend toujours',
            'lines' => [
                'C\'est notre dernier message à ce sujet : nous ne voulons pas vous déranger.',
                'Votre compte'.($user->level ? ', votre niveau ('.$user->level->value.')' : '').' et ce que vous avez commencé sont gardés : avec une formule, vous reprenez là où vous vous êtes arrêté.',
                'Une question avant de choisir ? Écrivez-nous sur WhatsApp : nous vous répondons.',
                $from,
            ],
            'button' => 'Choisir ma formule',
            'url' => route('payment.plans'),
            'review' => null,
            'links' => [],
        ];
    }

    /**
     * Relances d'un ancien abonné dont la formule est finie sans renouvellement (null : plus d'actualité).
     *
     * @return array<string, mixed>|null
     */
    private static function lapsed(string $campaign, User $user): ?array
    {
        $subscription = self::lapsedSubscription($user);

        if ($subscription === null) {
            return null;
        }

        $teacher = Teachers::forUser($user)->name();
        $report = SubscriptionReport::for($subscription);
        $plan = Plan::find((string) $subscription->plan);
        $methods = LessonReminders::paymentMethods($user);
        $pay = $plan !== null
            ? 'Pour reprendre, renouvelez votre formule '.$plan->name.' ('.Money::format($plan->price).') depuis votre téléphone'.($methods ? ', par '.$methods : '').' ; rien n\'est prélevé automatiquement.'
            : 'Pour reprendre, choisissez une formule depuis votre téléphone ; rien n\'est prélevé automatiquement.';
        $button = $plan !== null ? 'Renouveler ma formule '.$plan->name : 'Choisir ma formule';
        $url = $plan !== null ? route('payment.create', $plan->key) : route('payment.plans');
        $facts = array_values(array_filter($report->lines(), fn (string $line) => ! str_starts_with($line, 'À retenir')));
        $errors = array_values(array_filter($report->lines(), fn (string $line) => str_starts_with($line, 'À retenir')));

        return match ($campaign) {
            LearnerMail::LAPSED_1 => [
                'subject' => 'On reprend vos séances ?',
                'title' => "Vos séances avec {$teacher} vous attendent",
                'lines' => array_merge(
                    ['Votre formule '.$subscription->plan_name.' s\'est terminée il y a quelques jours.'],
                    array_slice($facts, 0, 2),
                    [$report->encouragement(), $pay],
                ),
                'button' => $button, 'url' => $url, 'review' => null, 'links' => [],
            ],
            LearnerMail::LAPSED_2 => [
                'subject' => 'Ne laissez pas vos progrès s\'arrêter',
                'title' => 'Votre anglais, où en êtes-vous ?',
                'lines' => array_merge(
                    [self::goalLine($user), self::regularityLine($teacher)],
                    array_slice($errors, 0, 1),
                    [$pay],
                ),
                'button' => $button, 'url' => $url, 'review' => null, 'links' => self::promoLinks(),
            ],
            default => [
                'subject' => "Un dernier message de {$teacher}",
                'title' => 'Vous êtes toujours le bienvenu',
                'lines' => [
                    'C\'est notre dernier message à ce sujet : nous ne voulons pas vous déranger.',
                    'Votre niveau, votre programme et vos erreurs à revoir sont gardés : vous reprenez là où vous vous êtes arrêté.',
                    'Une question, ou un souci avec le paiement ? Écrivez-nous sur WhatsApp : nous vous répondons.',
                    $pay,
                ],
                'button' => $button, 'url' => $url, 'review' => null, 'links' => [],
            ],
        };
    }

    /** Pourquoi l'anglais, d'après l'objectif choisi au questionnaire (sinon les raisons les plus courantes). */
    private static function goalLine(User $user): string
    {
        $goal = $user->activeProgram()->first()?->goal;

        return $goal instanceof Goal
            ? 'Vous nous avez dit vouloir : « '.$goal->label().' ». Votre programme personnel est construit pour cela, leçon après leçon.'
            : 'L\'anglais ouvre des portes : un meilleur travail, des études ou un stage, des clients et des visiteurs anglophones, et surtout la confiance de parler sans gêne.';
    }

    private static function regularityLine(string $teacher): string
    {
        return "Ce qui fait vraiment progresser, c'est la régularité : quelques minutes par jour à l'oral, depuis votre téléphone, à l'heure qui vous arrange. {$teacher} vous corrige avec patience, sans gêne ni jugement.";
    }

    /**
     * Liens vers nos vidéos (« Vos questions, mes réponses » quand sa version du site est prête, TikTok, chaîne
     * WhatsApp), montrés sous le bouton de certaines relances.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function promoLinks(): array
    {
        $links = [];
        $key = config('videos.presentations.site.plans_link');
        $video = $key ? app(SiteVideos::class)->find((string) $key) : null;

        if ($video !== null) {
            $links[] = ['label' => 'Regarder la vidéo « '.$video->title.' »', 'url' => route('how').'#'.$video->key];
        }

        if (filled(config('platform.contact.tiktok'))) {
            $links[] = ['label' => 'Nos leçons en vidéo sur TikTok', 'url' => (string) config('platform.contact.tiktok')];
        }

        if (filled(config('platform.contact.whatsapp_channel'))) {
            $links[] = ['label' => 'La chaîne WhatsApp de '.config('platform.name'), 'url' => (string) config('platform.contact.whatsapp_channel')];
        }

        return $links;
    }

    /**
     * Message WhatsApp prêt pour l'équipe (« Relances »), d'après l'étape de l'inscrit, au nom de l'équipe.
     */
    public static function whatsappMessage(User $user): string
    {
        $first = LearnerName::first($user);
        $hello = 'Bonjour'.($first ? ' '.$first : '').', ici l\'équipe '.config('platform.name').'.';
        $minutes = Trial::minutes();
        $teachers = Teachers::names();
        $space = route('dashboard');

        return $hello.' '.match (self::stage($user)) {
            self::STAGE_NO_ASSESSMENT => 'Vous avez créé votre compte : votre évaluation orale gratuite de '.config('platform.assessment.max_minutes')." minutes avec {$teachers} vous attend, puis {$minutes} minutes de vrai cours offertes pour tout découvrir. Connectez-vous ici : {$space}",
            self::STAGE_NO_TRIAL => 'Bravo pour votre évaluation'.($user->level ? ' (niveau '.$user->level->value.')' : '')." ! Vos {$minutes} minutes de cours offertes vous attendent : programme personnel, leçon au tableau, pratique à l'oral et aide à vos cours d'anglais. Touchez « Commencer mon essai gratuit » dans votre espace : {$space}",
            self::STAGE_ON_TRIAL => 'Votre séance d\'essai gratuite est ouverte jusqu\'au '.Duration::at($user->activeSubscription->ends_at, false).'. Profitez-en : touchez « Parler avec '.Teachers::forUser($user)->name()." » dans votre espace : {$space}",
            self::STAGE_TRIAL_ENDED => "Merci d'avoir essayé un cours avec {$teachers} ! Avez-vous une question avant de continuer ? Les formules sont ici : ".route('payment.plans'),
            default => "Avez-vous une question sur votre espace ? Nous sommes là pour vous aider : {$space}",
        };
    }
}
