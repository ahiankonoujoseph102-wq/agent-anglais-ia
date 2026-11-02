<?php

namespace App\Support\Reviews;

use App\Enums\Role;
use App\Models\LearnerMail;
use App\Models\User;
use App\Support\Live\WorkingTogether;
use App\Support\Teachers\Teachers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Invitations par e-mail à laisser un avis (demande du fondateur, 2 novembre 2026 : un seul avis sur le site).
 * Tout élève qui peut en laisser un (évaluation finie ou séance du jour tenue ; abonné, essai ou gratuit) et ne l'a
 * pas fait : un premier e-mail quelques heures après sa première expérience (platform.reviews.email_after_hours),
 * un rappel quelques jours après (email_reminder_days), puis plus rien. Envoyés par learners:follow-up, en petit
 * lot (emails_per_day), avec le lien « Ne plus recevoir ces e-mails ». Jamais une demande de bonne note : les
 * minutes offertes le sont quelle que soit la note.
 */
final class ReviewInvitations
{
    /** @return Builder<User> */
    public static function candidates(): Builder
    {
        $held = WorkingTogether::heldSeconds();

        return User::query()
            ->where('role', Role::Learner->value)
            ->whereNull('follow_up_opt_out_at')
            ->whereDoesntHave('review')
            ->where(fn (Builder $query) => $query
                ->whereHas('assessment', fn (Builder $assessment) => $assessment->whereNotNull('level'))
                ->orWhereHas('lessons', fn (Builder $lesson) => $lesson->where('used_seconds', '>=', $held)));
    }

    /** L'élève doit-il encore recevoir une invitation (au moment de l'envoi) ? */
    public static function wanted(User $user): bool
    {
        return $user->follow_up_opt_out_at === null && Reviews::shouldInvite($user);
    }

    /** Première expérience qui permet de donner un avis : fin de l'évaluation ou première séance du jour tenue. */
    public static function firstExperience(User $user): ?Carbon
    {
        $times = array_filter([
            $user->assessment()->whereNotNull('level')->value('ended_at'),
            $user->lessons()->where('used_seconds', '>=', WorkingTogether::heldSeconds())->min('ended_at')
                ?? $user->lessons()->where('used_seconds', '>=', WorkingTogether::heldSeconds())->min('created_at'),
        ]);

        return $times === [] ? null : Carbon::parse(min(array_map(fn ($time) => Carbon::parse($time)->getTimestamp(), $times)));
    }

    /** Met en file les invitations dues (une par campagne et par compte). */
    public static function queue(): int
    {
        $config = (array) config('platform.reviews');
        $queued = 0;

        self::candidates()
            ->with('learnerMails')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($config, &$queued) {
                foreach ($users as $user) {
                    $mails = $user->learnerMails->whereIn('campaign', LearnerMail::REVIEWS);
                    $first = $mails->firstWhere('campaign', LearnerMail::REVIEW_1);
                    $campaign = null;

                    if ($first === null) {
                        $since = self::firstExperience($user);
                        $campaign = $since !== null && $since->lte(now()->subHours((int) $config['email_after_hours'])) ? LearnerMail::REVIEW_1 : null;
                    } elseif ($first->status === LearnerMail::SENT && $first->sent_at?->lte(now()->subDays((int) $config['email_reminder_days']))
                        && $mails->firstWhere('campaign', LearnerMail::REVIEW_2) === null) {
                        $campaign = LearnerMail::REVIEW_2;
                    }

                    if ($campaign !== null) {
                        $user->learnerMails()->firstOrCreate(['campaign' => $campaign], ['status' => LearnerMail::PENDING]);
                        $queued++;
                    }
                }
            });

        return $queued;
    }

    /**
     * Texte de l'invitation : uniquement des faits (ce que l'élève a fait), les minutes offertes quelle que soit la
     * note, et cinq étoiles à toucher (/mon-avis?note=N).
     *
     * @return array{subject: string, title: string, lines: list<string>, button: string, url: string, review: null, links: list<array{label: string, url: string}>, stars: list<string>}
     */
    public static function content(string $campaign, User $user): ?array
    {
        if (! Reviews::shouldInvite($user)) {
            return null;
        }

        $teacher = Teachers::forUser($user)->name();
        $platform = (string) config('platform.name');
        $minutes = intdiv(TimeBonuses::seconds(), 60);
        $held = $user->lessons()->where('used_seconds', '>=', WorkingTogether::heldSeconds())->count();
        $done = match (true) {
            $held > 1 => "Vous avez déjà fait {$held} séances avec {$teacher}.",
            $held === 1 => "Vous avez fait votre première séance avec {$teacher}.",
            default => "Vous avez passé votre évaluation orale gratuite avec {$teacher}.",
        };
        $gift = $minutes > 0 ? "Pour vous remercier, vous recevez {$minutes} minutes de cours offertes, quelle que soit votre note".($user->activeSubscription === null ? ', ajoutées à votre première séance avec une formule.' : '.') : null;
        $stars = array_map(fn (int $note) => route('reviews.edit', ['note' => $note]), range(1, 5));

        $body = $campaign === LearnerMail::REVIEW_2
            ? [
                'subject' => 'Petit rappel : votre avis sur '.$platform,
                'title' => 'Avez-vous deux minutes pour donner votre avis ?',
                'lines' => array_values(array_filter([
                    $done,
                    'Votre avis, positif comme négatif, est publié tel que vous l\'écrivez : il aide d\'autres personnes à oser commencer, et il nous aide à nous améliorer.',
                    $gift,
                ])),
            ]
            : [
                'subject' => 'Votre avis compte : comment se passe '.$platform.' pour vous ?',
                'title' => 'Votre avis compte',
                'lines' => array_values(array_filter([
                    $done,
                    'Dites-nous ce que vous en pensez, en toute honnêteté : ce qui vous aide, et ce qui pourrait être mieux. Votre avis, positif comme négatif, aide d\'autres personnes à oser commencer.',
                    'Cela prend deux minutes : une note de 1 à 5 étoiles et quelques mots.',
                    $gift,
                ])),
            ];

        return $body + [
            'button' => 'Donner mon avis',
            'url' => route('reviews.edit'),
            'review' => null,
            'links' => [],
            'stars' => $stars,
        ];
    }
}
