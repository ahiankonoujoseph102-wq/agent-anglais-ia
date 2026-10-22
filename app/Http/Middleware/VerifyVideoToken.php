<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API des vidéos (GitHub Actions) : en-tête « Authorization: Bearer … » égal à VIDEO_UPLOAD_TOKEN, comparé en
 * temps constant. Sans jeton réglé, tout est refusé. Le jeton n'est jamais écrit dans un journal ni une réponse.
 */
class VerifyVideoToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('videos.token');

        if (! is_string($token) || strlen($token) < 32) {
            return response()->json(['message' => 'Jeton des vidéos non réglé (VIDEO_UPLOAD_TOKEN).'], 503);
        }

        if (! is_string($request->bearerToken()) || ! hash_equals($token, $request->bearerToken())) {
            return response()->json(['message' => 'Jeton refusé.'], 401);
        }

        return $next($request);
    }
}
