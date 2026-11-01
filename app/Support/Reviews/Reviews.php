<?php

namespace App\Support\Reviews;

use App\Enums\ReviewStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Review;
use App\Models\User;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use App\Support\Live\WorkingTogether;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Avis des élèves. Un par compte : élève gratuit après son évaluation, élève abonné après une séance du jour.
 * Publié tout de suite, positif comme négatif, sauf une note très basse (platform.reviews.hold_rating_at_most) ou un
 * texte qui accroche une vérification automatique (lien, numéro, e-mail, mot grossier, marque) : il attend alors
 * l'administrateur, prévenu par une alerte. 5 minutes offertes pour tout avis, quelle que soit la note, une seule fois.
 * Rien n'est jamais inventé ni réécrit : l'administrateur publie, retire ou répond, jamais il ne modifie le texte.
 */
final class Reviews
{
    /** Peut laisser (ou modifier) un avis : jamais l'équipe ; une évaluation finie ou une séance du jour tenue. */
    public static function canReview(?User $user): bool
    {
        if ($user === null || $user->isStaff()) {
            return false;
        }

        return $user->assessment()->whereNotNull('level')->exists()
            || $user->lessons()->where('used_seconds', '>=', WorkingTogether::heldSeconds())->exists();
    }

    /** Invitation montrée (« Mon espace », page de résultat) : peut laisser un avis et ne l'a pas encore fait. */
    public static function shouldInvite(?User $user): bool
    {
        return self::canReview($user) && ! $user->review()->exists();
    }

    /**
     * Enregistre l'avis (nouveau ou modifié) et décide s'il est publié tout de suite.
     *
     * @return array{review: Review, bonus: bool}
     */
    public static function save(User $user, int $rating, string $body): array
    {
        $body = self::clean($body);
        $flags = self::flags($rating, $body);

        $result = DB::transaction(function () use ($user, $rating, $body, $flags) {
            $review = $user->review()->lockForUpdate()->first();
            $changed = $review === null || $review->rating !== $rating || $review->body !== $body;

            $review ??= new Review(['user_id' => $user->id]);
            $review->fill(['rating' => $rating, 'body' => $body] + self::snapshot($user));

            if ($changed) {
                $review->flags = $flags ?: null;
                // Un avis retiré par l'administrateur repasse TOUJOURS par lui une fois modifié.
                $held = $flags !== [] || $review->status === ReviewStatus::Hidden;
                $review->status = $held ? ReviewStatus::Pending : ReviewStatus::Published;
                $review->published_at = $held ? $review->published_at : ($review->published_at ?? now());
            }

            $review->save();

            return ['review' => $review, 'bonus' => TimeBonuses::grant($user) !== null, 'alert' => $changed && $review->isPending()];
        });

        // Avis qui attend une vérification (nouveau ou modifié) : l'administrateur est prévenu.
        if ($result['alert']) {
            AdminAlerts::dispatch(AlertBuilder::reviewPending($result['review']->load('user')));
        }

        return ['review' => $result['review'], 'bonus' => $result['bonus']];
    }

    /**
     * Raisons de faire attendre l'avis (vides : publié tout de suite).
     *
     * @return list<string>
     */
    public static function flags(int $rating, string $body): array
    {
        $flags = [];
        $plain = ' '.Str::lower(Str::ascii($body)).' ';
        $words = ' '.trim((string) preg_replace('/[^a-z0-9]+/', ' ', $plain)).' ';

        if ($rating <= (int) config('platform.reviews.hold_rating_at_most')) {
            $flags[] = 'low_rating';
        }

        if (preg_match('~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|info|io|me|ly|fr|tg|bj|ci|bf|app|link)\b~i', $plain)) {
            $flags[] = 'link';
        }

        if (preg_match('/(?:\d[\s.\-]?){8,}/', $body)) {
            $flags[] = 'phone';
        }

        if (preg_match('/[^\s@]+@[^\s@]+\.[a-z]{2,}/i', $body)) {
            $flags[] = 'email';
        }

        foreach ([['words', 'blocked_words'], ['brand', 'brand_words']] as [$flag, $list]) {
            foreach ((array) config('platform.reviews.'.$list) as $word) {
                $word = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $word))));

                if ($word !== '' && str_contains($words, ' '.$word.' ')) {
                    $flags[] = $flag;
                    break;
                }
            }
        }

        return $flags;
    }

    public static function publish(Review $review, User $admin): void
    {
        $review->forceFill([
            'status' => ReviewStatus::Published,
            'published_at' => $review->published_at ?? now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
    }

    public static function hide(Review $review, User $admin): void
    {
        $review->forceFill([
            'status' => ReviewStatus::Hidden,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /** Réponse publique de l'équipe sous l'avis (vide : retirée). */
    public static function reply(Review $review, ?string $reply): void
    {
        $reply = self::clean((string) $reply);

        $review->forceFill([
            'reply' => $reply === '' ? null : $reply,
            'replied_at' => $reply === '' ? null : now(),
        ])->save();
    }

    /**
     * Avis publiés, les plus récents d'abord.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public static function published()
    {
        return Review::query()->published()->with('user')
            ->latest('published_at')->latest('id')
            ->paginate((int) config('platform.reviews.per_page'));
    }

    /**
     * Quelques avis récents et positifs pour l'accueil (4 étoiles ou plus), seulement à partir de home_min avis publiés.
     *
     * @return Collection<int, Review>
     */
    public static function forHome(): Collection
    {
        if (self::stats()['count'] < (int) config('platform.reviews.home_min')) {
            return collect();
        }

        return Review::query()->published()->where('rating', '>=', 4)->with('user')
            ->latest('published_at')->latest('id')
            ->limit((int) config('platform.reviews.home_count'))
            ->get();
    }

    /**
     * Nombre d'avis publiés et note moyenne (seulement à partir de average_min avis, jamais arrondie vers le haut).
     *
     * @return array{count: int, average: float|null}
     */
    public static function stats(): array
    {
        $row = Review::query()->published()->selectRaw('count(*) as total, avg(rating) as average')->first();
        $count = (int) ($row->total ?? 0);
        $average = $count >= (int) config('platform.reviews.average_min') ? floor((float) $row->average * 10) / 10 : null;

        return ['count' => $count, 'average' => $average];
    }

    /** Nombre d'avis qui attendent l'administrateur (badge de l'onglet « Avis »). */
    public static function pendingCount(): int
    {
        return Review::query()->where('status', ReviewStatus::Pending)->count();
    }

    /**
     * Ce que l'avis montre de l'élève, figé à l'envoi : sorte d'élève, niveau, pays.
     *
     * @return array{kind: string, level: string|null, country: string|null}
     */
    private static function snapshot(User $user): array
    {
        $subscriptions = $user->subscriptions();

        $kind = match (true) {
            $user->ambassador()->exists() => 'ambassador',
            (clone $subscriptions)->paid()->whereNull('ambassador_id')->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Expired])->exists() => 'subscriber',
            (clone $subscriptions)->whereNotNull('granted_days')->exists() => 'granted',
            default => 'free',
        };

        return ['kind' => $kind, 'level' => $user->level?->value, 'country' => $user->countryCode()];
    }

    /** Espaces et sauts de ligne remis en ordre ; rien d'autre ne change dans le texte de l'élève. */
    private static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags($text));
        $text = (string) preg_replace("/[ \t]+/u", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);

        return trim($text);
    }
}
