<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\CourseSession;
use App\Support\CourseReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Document personnel de révision d'une séance « Mes cours » : téléchargement (son propriétaire et l'administrateur
 * seulement, jamais d'adresse publique), état (la page se met à jour toute seule) et « Réessayer ». Toujours
 * accessible, même quand la formule est terminée : le document appartient à l'élève.
 */
class CourseReviewController extends Controller
{
    public function download(Request $request, CourseSession $session): Response
    {
        $this->authorizeSession($request, $session);
        $review = $session->review()->with('lesson')->first();
        $pdf = $review !== null ? CourseReviews::file($review) : null;

        abort_if($pdf === null, 404);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.CourseReviews::filename($review).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function status(Request $request, CourseSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json(['state' => CourseReviews::stateOf($session)]);
    }

    public function retry(Request $request, CourseSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        $review = $session->review()->first();
        $key = 'course-review-retry:'.$request->user()->id;

        if ($review === null || ! $review->isFailed()) {
            return back();
        }

        if (RateLimiter::tooManyAttempts($key, (int) CourseReviews::setting('retries_per_hour'))) {
            return back()->with('status', 'Vous avez déjà demandé plusieurs essais. Réessayez dans une heure, ou écrivez-nous sur WhatsApp.');
        }

        RateLimiter::hit($key, 3600);
        CourseReviews::retry($review);

        return back()->with('status', 'C\'est noté : votre document de révision est de nouveau en préparation.');
    }

    private function authorizeSession(Request $request, CourseSession $session): void
    {
        $user = $request->user();

        abort_unless($session->user_id === $user->id || $user->isAdmin(), 404);
    }
}
