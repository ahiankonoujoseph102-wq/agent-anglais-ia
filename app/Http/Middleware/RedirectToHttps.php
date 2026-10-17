<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige toute requête http:// vers https:// quand « app.force_https » est actif (production).
 * Le protocole réel est celui que transmet le proxy de confiance (voir config/trustedproxy.php).
 * Le contrôle de santé « /up » n'est pas dans le groupe web : il n'est donc jamais redirigé.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
