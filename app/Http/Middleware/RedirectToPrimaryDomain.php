<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Changement de domaine : une requête arrivée sur une ancienne adresse (« app.redirect_from_hosts »,
 * variable REDIRECT_FROM_HOSTS) est redirigée en 301 vers la même page sur APP_URL, pour que Google
 * transfère le référencement. Seules les lectures (GET, HEAD) sont redirigées ; le contrôle de santé
 * « /up » ne l'est jamais.
 */
class RedirectToPrimaryDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = (array) config('app.redirect_from_hosts');
        $primary = strtolower((string) parse_url(Seo::base(), PHP_URL_HOST));

        if ($hosts !== []
            && $request->isMethodSafe()
            && ! $request->is('up')
            && in_array(strtolower($request->getHost()), $hosts, true)
            && strtolower($request->getHost()) !== $primary) {
            return redirect()->away(Seo::base().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
