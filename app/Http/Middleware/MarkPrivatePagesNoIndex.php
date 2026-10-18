<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tête « X-Robots-Tag: noindex » sur toutes les pages qui ne sont pas publiques (en plus de la balise
 * <meta name="robots"> du gabarit) : espace apprenant, séances, paiement, « Mes cours », bienvenue, administration…
 */
class MarkPrivatePagesNoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Seo::indexable($request) && ! $request->routeIs('seo.*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
