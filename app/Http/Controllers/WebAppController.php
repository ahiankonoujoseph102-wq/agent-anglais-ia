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
    /**
     * Captures d'écran de la fenêtre d'installation de Chrome sur Android (fenêtre « riche », qui montre clairement
     * qu'on installe une application). Faites avec un compte de démonstration : aucune donnée personnelle, aucune marque
     * d'intelligence artificielle. Fichiers produits EN LOCAL par tools/brand/app-screenshots.mjs.
     */
    public const SCREENSHOTS = [
        'images/app/ecran-mon-espace.webp' => 'Mon espace : la prochaine étape et le temps du jour',
        'images/app/ecran-seance.webp' => 'La séance du jour avec :teacher et le tableau',
    ];

    public function manifest(): JsonResponse
    {
        $name = config('platform.name');
        $icons = fn (string $file, int $size, string $purpose) => [
            'src' => asset("icons/{$file}"),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        $screenshots = [];

        foreach (self::SCREENSHOTS as $file => $label) {
            $size = @getimagesize(public_path($file));

            if ($size !== false) {
                $screenshots[] = [
                    'src' => asset($file),
                    'sizes' => "{$size[0]}x{$size[1]}",
                    'type' => 'image/webp',
                    'form_factor' => 'narrow',
                    'label' => str_replace(':teacher', (string) config('avatar.model.name'), $label),
                ];
            }
        }

        return response()->json([
            // Identité de l'application : ne JAMAIS la changer (ce serait une autre application pour le téléphone).
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
            'screenshots' => $screenshots,
            // Permet au site de savoir que l'application est déjà installée (navigator.getInstalledRelatedApps).
            'related_applications' => [['platform' => 'webapp', 'url' => route('manifest')]],
            'prefer_related_applications' => false,
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function offline(): Response
    {
        return response()->view('offline');
    }
}
