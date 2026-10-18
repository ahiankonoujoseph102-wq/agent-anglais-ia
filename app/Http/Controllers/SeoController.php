<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt et sitemap.xml, écrits à partir de APP_URL (config app.url) : justes quel que soit le domaine.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            // Pages privées : rien à indexer (elles portent aussi noindex).
            foreach (Seo::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = 'Allow: /';
        } else {
            // Environnement d'essai : jamais dans les moteurs de recherche.
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.Seo::url('sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = array_map(fn (string $route) => Seo::url(route($route, [], false)), Seo::INDEXABLE_ROUTES);

        return response()->view('seo.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
