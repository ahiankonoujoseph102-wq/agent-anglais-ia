<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après un mot de passe provisoire, l'utilisateur doit en choisir un nouveau
 * avant de pouvoir faire quoi que ce soit d'autre.
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes encore accessibles tant que le mot de passe n'est pas changé.
     */
    private const ALLOWED = ['password.change', 'password.update', 'logout'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Choisissez d\'abord un nouveau mot de passe.'], 403);
        }

        return to_route('password.change');
    }
}
