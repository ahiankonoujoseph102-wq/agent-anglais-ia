<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;

/**
 * Référencement : l'adresse du site vient TOUJOURS de APP_URL (config app.url), jamais de la requête,
 * pour que les adresses partagées restent justes quel que soit le domaine (talkami.net…).
 * Seules les pages publiques de INDEXABLE_ROUTES sont indexées (sitemap.xml, canonique, Open Graph) ;
 * toutes les autres (espace apprenant, séances, paiement, « Mes cours », bienvenue, administration,
 * connexion, erreurs) portent noindex.
 */
final class Seo
{
    /** Pages publiques indexées, dans l'ordre du sitemap. */
    public const INDEXABLE_ROUTES = ['home', 'contact', 'register'];

    /** Image de partage (WhatsApp, Facebook…), 1200 × 630 pixels, produite par tools/brand/share-image.mjs. */
    public const SHARE_IMAGE = 'images/partage-talkami.jpg';

    /** Débuts d'adresse des pages privées, fermés aux robots (robots.txt). */
    public const PRIVATE_PATHS = [
        '/admin', '/espace', '/bienvenue', '/compte', '/abonnement', '/paiements', '/evaluation', '/programme',
        '/mes-cours', '/mes-seances', '/bilan-niveau', '/seance', '/attestations', '/mot-de-passe', '/deconnexion',
    ];

    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function url(string $path = ''): string
    {
        $path = trim($path, '/');

        return self::base().'/'.$path;
    }

    public static function indexable(?Request $request = null): bool
    {
        $route = ($request ?? request())->route();

        return $route !== null && in_array($route->getName(), self::INDEXABLE_ROUTES, true) && ($request ?? request())->isMethod('GET');
    }

    /**
     * Adresse canonique de la page : chemin de la requête, sans paramètres, sur l'adresse du site.
     */
    public static function canonical(?Request $request = null): string
    {
        $path = ($request ?? request())->path();

        return self::url($path === '/' ? '' : $path);
    }

    /**
     * Données structurées « Organization » : nom, adresse du site, logo, coordonnées publiques du centre.
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $contact = SiteSettings::contact();
        $countries = array_keys((array) config('payment.countries'));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('platform.name'),
            'url' => self::url(),
            'logo' => self::url('brand/logo-vertical.png'),
            'description' => config('platform.brand.tagline'),
            'email' => $contact['email'] ?: null,
            'address' => $contact['address'] ?: null,
            'contactPoint' => array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => $contact['phones'][0] ?? null,
                'availableLanguage' => ['French', 'English'],
                'areaServed' => $countries ?: null,
            ]) ?: null,
        ]);
    }

    /**
     * Données structurées « FAQPage » tirées du HTML des questions fréquentes (<details> : <summary> =
     * question, le reste = réponse) : exactement les textes affichés, jamais une copie à tenir à jour.
     *
     * @return array<string, mixed>|null
     */
    public static function faq(string $html): ?array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $questions = [];

        foreach ($xpath->query('//details') as $details) {
            $summary = $xpath->query('./summary', $details)->item(0);

            if ($summary === null) {
                continue;
            }

            $question = self::text($summary->textContent);
            $summary->parentNode->removeChild($summary);
            $answer = self::text($details->textContent);

            if ($question !== '' && $answer !== '') {
                $questions[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
            }
        }

        return $questions === [] ? null : ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    /**
     * Bloc <script type="application/ld+json"> sûr (aucun « </script> » possible dans le texte).
     */
    public static function jsonLd(?array $data): string
    {
        if ($data === null) {
            return '';
        }

        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
    }

    private static function text(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
