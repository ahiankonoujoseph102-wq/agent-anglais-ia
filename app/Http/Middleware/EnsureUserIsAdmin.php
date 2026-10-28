<?php

namespace App\Http\Middleware;

use App\Support\Admin\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Administrateur principal : tout. Assistant : seulement les pages permises (AdminAccess), même une adresse
        // tapée à la main.
        abort_unless(AdminAccess::allows($request->user(), $request->route()?->getName()), 403, 'Accès réservé aux administrateurs.');

        return $next($request);
    }
}
