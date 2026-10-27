<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Changement de domaine : une requête arrivée sur une ancienne adresse (« app.redirect_from_hosts »,
 * variable REDIRECT_FROM_HOSTS) est redirigée en 301 vers la même page sur APP_URL (chemin et paramètres
 * gardés), pour que Google transfère le référencement et que les anciens liens partagés marchent.
 * Seules les lectures (GET, HEAD) de pages sont redirigées. Jamais redirigés (ils marchent sur les deux
 * adresses) : les envois (POST, PUT, PATCH, DELETE), les services automatiques (UNREDIRECTED_PATHS : API des
 * workflows GitHub, contrôle de santé, fichiers de l'application installée) et les adresses signées (leur
 * signature porte sur l'adresse d'origine).
 */
class RedirectToPrimaryDomain
{
    /** Chemins jamais redirigés. */
    public const UNREDIRECTED_PATHS = ['up', 'api', 'api/*', 'sw.js', 'manifest.webmanifest', 'hors-connexion'];

    public function handle(Request $request, Closure $next): Response
    {
        $hosts = (array) config('app.redirect_from_hosts');
        $primary = strtolower((string) parse_url(Seo::base(), PHP_URL_HOST));
        $host = strtolower($request->getHost());

        if ($hosts !== []
            && $primary !== ''
            && $request->isMethodSafe()
            && in_array($host, $hosts, true)
            && $host !== $primary
            && ! $request->is(...self::UNREDIRECTED_PATHS)
            && ! $request->query->has('signature')) {
            return redirect()->away(Seo::base().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
