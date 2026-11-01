<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\Reviews\Reviews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Onglet « Avis » (administrateur principal) : avis à vérifier, publiés, retirés. Publier, retirer, répondre ; le
 * texte de l'élève n'est JAMAIS modifié.
 */
class ReviewController extends Controller
{
    public const FILTERS = ['a-verifier' => ReviewStatus::Pending, 'publies' => ReviewStatus::Published, 'retires' => ReviewStatus::Hidden];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('etat'), self::FILTERS) ? (string) $request->query('etat') : null;

        $query = Review::query()->with(['user', 'reviewer'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('updated_at');

        if ($filter !== null) {
            $query->where('status', self::FILTERS[$filter]);
        }

        return view('admin.reviews.index', [
            'reviews' => $query->paginate(30)->withQueryString(),
            'filter' => $filter,
            'pending' => Reviews::pendingCount(),
            'stats' => Reviews::stats(),
        ]);
    }

    public function publish(Request $request, Review $review): RedirectResponse
    {
        Reviews::publish($review, $request->user());

        return redirect()->to(route('admin.reviews.index').'#avis-'.$review->id)->with('status', 'Avis publié.');
    }

    public function hide(Request $request, Review $review): RedirectResponse
    {
        Reviews::hide($review, $request->user());

        return redirect()->to(route('admin.reviews.index').'#avis-'.$review->id)->with('status', 'Avis retiré du site.');
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $reply = $request->validate(
            ['reply' => ['nullable', 'string', 'max:'.(int) config('platform.reviews.max_characters')]],
            ['reply.max' => 'La réponse est trop longue.'],
        )['reply'] ?? '';

        Reviews::reply($review, (string) $reply);

        return redirect()->to(route('admin.reviews.index').'#avis-'.$review->id)
            ->with('status', trim((string) $reply) === '' ? 'Réponse retirée.' : 'Réponse enregistrée : elle s\'affiche sous l\'avis.');
    }
}
