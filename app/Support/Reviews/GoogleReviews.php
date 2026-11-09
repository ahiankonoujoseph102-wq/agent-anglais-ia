<?php

namespace App\Support\Reviews;

use App\Enums\Role;
use App\Models\LearnerMail;
use App\Models\User;
use App\Support\Reminders\LearnerPush;
use App\Support\Teachers\Teachers;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demande d'avis sur la fiche Google de l'établissement (demande du fondateur, 9 novembre 2026) : UNE fois par
 * compte, à tout élève inscrit qui n'a pas refusé nos e-mails ; un e-mail (campagne « google_review », envoyé par lots
 * par learners:follow-up, dans la limite des relances) et une notification sur ses téléphones abonnés. Règles de
 * Google : la même demande à TOUS (jamais seulement aux élèves contents), aucune récompense, aucune note suggérée.
 */
final class GoogleReviews
{
    public static function url(): string
    {
        return (string) config('platform.reviews.google_url');
    }

    /** Élèves qui n'ont pas encore reçu la demande. @return Builder<User> */
    public static function targets(): Builder
    {
        return User::query()
            ->where('role', Role::Learner->value)
            ->whereNull('follow_up_opt_out_at')
            ->whereDoesntHave('learnerMails', fn (Builder $query) => $query->where('campaign', LearnerMail::GOOGLE_REVIEW));
    }

    /**
     * Met la demande en file pour chaque élève visé et envoie aussitôt la notification sur ses téléphones.
     *
     * @return array{queued: int, push_sent: int}
     */
    public static function launch(): array
    {
        $result = ['queued' => 0, 'push_sent' => 0];

        self::targets()->orderBy('id')->chunkById(200, function ($users) use (&$result) {
            foreach ($users as $user) {
                $mail = $user->learnerMails()->firstOrCreate(['campaign' => LearnerMail::GOOGLE_REVIEW], ['status' => LearnerMail::PENDING]);

                if (! $mail->wasRecentlyCreated) {
                    continue;
                }

                $result['queued']++;

                if (LearnerPush::send($user, self::notification($user)) === 'sent') {
                    $result['push_sent']++;
                }
            }
        });

        return $result;
    }

    /** @return array{title: string, body: string, url: string, tag: string} */
    public static function notification(User $user): array
    {
        return [
            'title' => 'Votre avis sur '.config('platform.name'),
            'body' => 'Une minute pour laisser votre avis sur Google ? Il aide d\'autres personnes à oser apprendre l\'anglais. Merci ! '.Teachers::forUser($user)->name(),
            'url' => route('reviews.google', absolute: false),
            'tag' => 'avis-google',
        ];
    }

    /**
     * Texte de l'e-mail : la même demande pour tous, sans récompense ni note suggérée.
     *
     * @return array{subject: string, title: string, lines: list<string>, button: string, url: string, review: null, links: list<array{label: string, url: string}>, stars: list<string>}
     */
    public static function content(User $user): array
    {
        $platform = (string) config('platform.name');
        $teacher = Teachers::forUser($user)->name();

        return [
            'subject' => 'Une minute pour donner votre avis sur '.$platform.' ?',
            'title' => 'Votre avis sur Google nous aide beaucoup',
            'lines' => [
                "Merci d'avoir créé votre compte sur {$platform} et d'apprendre l'anglais avec {$teacher}.",
                "Pourriez-vous laisser votre avis sur notre page Google ? Dites simplement, avec vos mots, ce que vous pensez de {$platform}, positif comme négatif.",
                'Votre avis aide d\'autres personnes à nous trouver et à oser parler anglais. Cela prend une minute.',
            ],
            'button' => 'Laisser mon avis sur Google',
            'url' => self::url(),
            'review' => null,
            'links' => [],
            'stars' => [],
        ];
    }
}
