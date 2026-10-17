<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Application installable : manifeste web et page « hors connexion ».
 * Ces deux réponses ne dépendent d'aucun compte (routes sans session).
 */
class WebAppController extends Controller
{
    public function manifest(): JsonResponse
    {
        $name = config('platform.name');
        $icons = fn (string $file, int $size, string $purpose) => [
            'src' => asset("icons/{$file}"),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => config('platform.brand.short_name') ?: $name,
            'description' => config('platform.brand.tagline'),
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => route('dashboard', [], false),
            'scope' => '/',
            // Plein écran de l'application, sans barre d'adresse.
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'theme_color' => config('platform.brand.theme_color'),
            'background_color' => config('platform.brand.background_color'),
            'categories' => ['education'],
            'icons' => [
                $icons('icon-192.png', 192, 'any'),
                $icons('icon-512.png', 512, 'any'),
                $icons('icon-maskable-512.png', 512, 'maskable'),
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function offline(): Response
    {
        return response()->view('offline');
    }
}
