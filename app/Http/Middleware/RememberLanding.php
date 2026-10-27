<?php

namespace App\Http\Middleware;

use App\Support\Origins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Première page vue par un visiteur : gardée dans sa session pour la provenance des inscrits (App\Support\Origins).
 */
class RememberLanding
{
    public function handle(Request $request, Closure $next): Response
    {
        Origins::remember($request);

        return $next($request);
    }
}
