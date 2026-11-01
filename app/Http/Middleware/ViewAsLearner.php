<?php

namespace App\Http\Middleware;

use App\Support\Admin\ViewAs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Voir son espace » (App\Support\Admin\ViewAs) : pendant une consultation, les pages de l'apprenant (tout sauf
 * /admin) sont montrées avec son compte, le temps de la requête, et en LECTURE SEULE.
 */
class ViewAsLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        ViewAs::end();

        if (! $request->hasSession() || ! $request->session()->has(ViewAs::SESSION_KEY) || $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $admin = Auth::guard('web')->user();
        $learner = ViewAs::learnerFor($request, $admin);

        if ($learner === null) {
            return $next($request);
        }

        // Lecture seule : aucun envoi, et aucune page qui écrit ou appelle un fournisseur en s'ouvrant.
        if (! $request->isMethodSafe() || $request->routeIs(...ViewAs::BLOCKED_ROUTES)) {
            return $request->expectsJson()
                ? response()->json(['message' => ViewAs::READ_ONLY], 403)
                : redirect()->to(url()->previous(route('dashboard')))->with('status', ViewAs::READ_ONLY);
        }

        Auth::guard('web')->setUser($learner);
        ViewAs::begin($learner);

        try {
            return $next($request);
        } finally {
            // La session reste celle de l'administrateur : son compte revient dès la page envoyée.
            ViewAs::end();
            Auth::guard('web')->setUser($admin);
        }
    }
}
