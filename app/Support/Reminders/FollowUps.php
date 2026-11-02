<?php

namespace App\Support\Reminders;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Mail\FollowUpMail;
use App\Models\LearnerMail;
use App\Models\User;
use App\Support\Duration;
use App\Support\LearnerName;
use App\Support\MailQuota;
use App\Support\Money;
use App\Support\Plan;
use App\Support\Redact;
use App\Support\Teachers\Teachers;
use App\Support\Trial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Relances des inscrits qui n'ont pas encore de formule payée : un e-mail par étape (inscrit sans évaluation,
 * évaluation sans essai, essai terminé sans formule) et le lancement de l'offre (learners:offer-trial). Chaque
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

        return $queued;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function dueCampaign(User $user, array $config): ?string
    {
        $fresh = User::query()->find($user->id);

        return match (self::stage($fresh)) {
            self::STAGE_NO_ASSESSMENT => $user->created_at->lte(now()->subHours((int) $config['no_assessment_after_hours'])) ? LearnerMail::NO_ASSESSMENT : null,
            self::STAGE_NO_TRIAL => ($user->assessment?->ended_at ?? $user->assessment?->created_at)?->lte(now()->subHours((int) $config['no_trial_after_hours'])) ? LearnerMail::NO_TRIAL : null,
            self::STAGE_TRIAL_ENDED => Trial::latest($fresh)?->ends_at->lte(now()->subHours((int) $config['trial_ended_after_hours'])) ? LearnerMail::TRIAL_ENDED : null,
            default => null,
        };
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

        $pending = LearnerMail::query()
            ->where('status', LearnerMail::PENDING)
            ->whereIn('campaign', LearnerMail::CAMPAIGNS)
            ->orderBy('id')
            ->limit((int) config('platform.follow_ups.batch') * 3)
            ->get();
        $sent = 0;

        foreach ($pending as $mail) {
            if ($sent >= (int) config('platform.follow_ups.batch') || ! self::allowsMail()) {
                break;
            }

            $user = User::query()->find($mail->user_id);
            $outcome = $user === null ? LearnerMail::SKIPPED : self::deliver($mail, $user);

            if ($outcome === null) {
                continue;
            }

            $result[$outcome === LearnerMail::NO_EMAIL ? 'no_email' : $outcome]++;
            $sent += $outcome === LearnerMail::SENT || $outcome === LearnerMail::FAILED ? 1 : 0;
        }

        return $result;
    }

    /** Envoie un e-mail ; renvoie son nouvel état, ou null pour le garder en attente (une autre relance trop récente). */
    private static function deliver(LearnerMail $mail, User $user): ?string
    {
        $content = self::content($mail->campaign, $user);

        if ($content === null || $user->follow_up_opt_out_at !== null || ! self::prospects()->whereKey($user->id)->exists()) {
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
            default => null,
        };

        if ($body === null) {
            return null;
        }

        if ($campaign === LearnerMail::OFFER) {
            array_unshift($body['lines'], "{$platform} s'est beaucoup amélioré : vous pouvez maintenant choisir votre professeur, {$teachers}, et chaque séance suit une vraie méthode (leçon, pratique, conversation, bilan).");
        }

        return $body + ['review' => null];
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
