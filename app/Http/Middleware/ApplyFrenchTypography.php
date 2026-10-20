<?php

namespace App\Http\Middleware;

use App\Support\Typography;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pages HTML : espaces insécables de la typographie française (App\Support\Typography), partout à la fois.
 * Les réponses JSON, les fichiers, les flux et les redirections ne sont jamais touchés.
 */
class ApplyFrenchTypography
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse || $response->isRedirection()) {
            return $response;
        }

        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $response->setContent(Typography::html($content));
        }

        return $response;
    }
}
