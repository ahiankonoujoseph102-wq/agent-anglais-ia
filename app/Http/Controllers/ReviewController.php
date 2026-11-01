<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reviews\SaveReviewRequest;
use App\Support\Reviews\Reviews;
use App\Support\Reviews\TimeBonuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Avis des élèves : page publique (/avis) et formulaire de l'élève (/mon-avis).
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('reviews.index', [
            'reviews' => Reviews::published(),
            'stats' => Reviews::stats(),
            'mine' => $request->user()?->review,
            'canReview' => Reviews::canReview($request->user()),
            'bonusMinutes' => intdiv(TimeBonuses::seconds(), 60),
        ]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! Reviews::canReview($user)) {
            return to_route('dashboard')->with('status', $user->isStaff()
                ? 'Les avis sont laissés par les élèves.'
                : 'Vous pourrez donner votre avis après votre évaluation gratuite.');
        }

        return view('reviews.edit', [
            'review' => $user->review,
            'bonusMinutes' => intdiv(TimeBonuses::seconds(), 60),
            'bonusGiven' => $user->timeBonuses()->exists(),
        ]);
    }

    public function save(SaveReviewRequest $request): RedirectResponse
    {
        ['review' => $review, 'bonus' => $bonus] = Reviews::save(
            $request->user(),
            (int) $request->validated('rating'),
            (string) $request->validated('body'),
        );

        $minutes = intdiv(TimeBonuses::seconds(), 60);
        $message = $review->isPublished()
            ? 'Merci ! Votre avis est publié.'
            : 'Merci ! Votre avis sera publié après une vérification par notre équipe.';

        if ($bonus) {
            $message .= $request->user()->activeSubscription !== null
                ? " {$minutes} minutes offertes sont ajoutées à votre temps du jour."
                : " {$minutes} minutes offertes vous attendent : elles s'ajouteront à votre première séance avec une formule.";
        }

        return to_route('dashboard')->with('status', $message);
    }
}
