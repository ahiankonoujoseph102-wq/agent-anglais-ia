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
    /** Pages publiques indexées, dans l'ordre du sitemap (« country » : une page par pays, CountryPage). */
    public const INDEXABLE_ROUTES = ['home', 'plans', 'about', 'how', 'courses-help', 'interview', 'guides.index', 'guides.show', 'country', 'ambassador.join', 'contact', 'register', 'privacy'];

    /** Types de données structurées autorisés : aucune note, aucun avis, aucun nombre d'apprenants. */
    public const SCHEMA_TYPES = ['Organization', 'WebSite', 'Person', 'Service', 'Offer', 'FAQPage', 'Question', 'Answer', 'ContactPoint', 'PostalAddress', 'Country', 'Article', 'BreadcrumbList', 'ListItem'];

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

    /**
     * Adresses complètes du sitemap : chaque page publique, chaque page pays.
     *
     * @return list<string>
     */
    public static function sitemapUrls(): array
    {
        $urls = [];

        foreach (self::INDEXABLE_ROUTES as $route) {
            $parameters = match ($route) {
                'country' => array_map(fn (string $slug) => ['country' => $slug], CountryPage::slugs()),
                'guides.show' => array_map(fn (string $slug) => ['guide' => $slug], Guides::slugs()),
                default => [[]],
            };

            foreach ($parameters as $parameter) {
                $urls[] = self::url(route($route, $parameter, false));
            }
        }

        return $urls;
    }

    public static function indexable(?Request $request = null): bool
    {
        $route = ($request ?? request())->route();

        // GET et HEAD : un outil qui ne demande que les en-têtes (HEAD) doit lire la même réponse que Google.
        return $route !== null && in_array($route->getName(), self::INDEXABLE_ROUTES, true) && in_array(($request ?? request())->method(), ['GET', 'HEAD'], true);
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
     * Données structurées « Organization » : nom, adresse du site, logo, coordonnées publiques du centre ;
     * avec $withProfiles, les comptes publics de la plateforme (sameAs).
     *
     * @return array<string, mixed>
     */
    public static function organization(bool $withProfiles = false): array
    {
        $contact = SiteSettings::contact();
        $countries = array_keys((array) config('payment.countries'));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::url().'#organisation',
            'name' => config('platform.name'),
            'url' => self::url(),
            'logo' => self::url('brand/logo-vertical.png'),
            'description' => config('platform.brand.tagline'),
            'email' => $contact['email'] ?: null,
            'address' => $contact['address'] ?: null,
            'founder' => array_filter(['@type' => 'Person', '@id' => self::url('a-propos').'#fondateur', 'name' => config('platform.founder.name')]),
            // Comptes publics (TikTok, chaîne WhatsApp) : seulement sur les pages qui les affichent (« À propos »),
            // jamais sur l'accueil, qui ne les montre pas (contact.social_routes).
            'sameAs' => $withProfiles ? (self::profiles() ?: null) : null,
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
     * Adresses des comptes publics de la plateforme (config/platform.php → contact) ; vides : absentes.
     *
     * @return list<string>
     */
    public static function profiles(): array
    {
        return array_values(array_filter([
            (string) config('platform.contact.tiktok'),
            (string) config('platform.contact.whatsapp_channel'),
        ]));
    }

    /**
     * Données structurées « WebSite » : nom et adresse du site, langue française.
     *
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => self::url().'#site',
            // Nom du site affiché par Google : le nom de la plateforme, et son nom de domaine comme autre nom.
            'name' => config('platform.name'),
            'alternateName' => (string) parse_url(self::url(), PHP_URL_HOST),
            'url' => self::url(),
            'inLanguage' => 'fr',
            'publisher' => ['@id' => self::url().'#organisation'],
        ];
    }

    /**
     * Données structurées « Person » : le fondateur (config/platform.php → founder), faits vérifiés seulement.
     *
     * @return array<string, mixed>
     */
    public static function founder(): array
    {
        $founder = (array) config('platform.founder');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => self::url('a-propos').'#fondateur',
            'name' => $founder['name'] ?? null,
            'jobTitle' => isset($founder['role']) ? $founder['role'].' de '.config('platform.name') : null,
            'url' => self::url('a-propos'),
            'worksFor' => ['@type' => 'Organization', '@id' => self::url().'#organisation', 'name' => config('platform.name'), 'url' => self::url()],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $founder['city'] ?? null, 'addressCountry' => $founder['country_code'] ?? null],
            'description' => Founder::bio(),
            'image' => ! empty($founder['photo']) && is_file(public_path($founder['photo'])) ? self::url($founder['photo']) : null,
        ]);
    }

    /**
     * Données structurées « Service » avec une « Offer » par formule : prix réels de la configuration,
     * en francs CFA d'Afrique de l'Ouest (code XOF), pays desservis = pays de paiement.
     *
     * @return array<string, mixed>
     */
    public static function offers(): array
    {
        $countries = array_values(array_map(fn (PaymentCountry $country) => ['@type' => 'Country', 'name' => $country->name], PaymentCountry::all()));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'Cours d\'anglais oral en ligne avec '.config('avatar.model.name'),
            'serviceType' => 'Cours d\'anglais en ligne',
            'provider' => ['@id' => self::url().'#organisation'],
            'areaServed' => $countries,
            'availableLanguage' => ['French', 'English'],
            'url' => self::url(route('plans', [], false)),
            'offers' => array_values(array_map(fn (Plan $plan) => [
                '@type' => 'Offer',
                'name' => $plan->name,
                'description' => $plan->dailyMinutes.' minutes par jour, valable '.$plan->durationDays.' jours',
                'price' => (string) $plan->price,
                'priceCurrency' => 'XOF',
                'url' => self::url(route('plans', [], false)),
                'availability' => 'https://schema.org/InStock',
            ], Plan::all())),
        ];
    }

    /**
     * Données structurées « Article » d'un guide gratuit : écrit et publié par la plateforme (aucun auteur inventé).
     *
     * @return array<string, mixed>
     */
    public static function article(Guides $guide): array
    {
        $url = self::url(route('guides.show', $guide->slug, false));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $guide->heading,
            'description' => $guide->description,
            'inLanguage' => 'fr',
            'datePublished' => $guide->published->toDateString(),
            'image' => self::url(self::SHARE_IMAGE),
            'mainEntityOfPage' => $url,
            'url' => $url,
            'author' => ['@type' => 'Organization', '@id' => self::url().'#organisation', 'name' => config('platform.name'), 'url' => self::url()],
            'publisher' => ['@type' => 'Organization', '@id' => self::url().'#organisation', 'name' => config('platform.name'), 'url' => self::url()],
        ];
    }

    /**
     * Fil d'Ariane (« BreadcrumbList ») : Accueil, puis les pages données (nom => chemin relatif), dans l'ordre.
     *
     * @param  array<string, string>  $pages
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $pages): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => self::url()]];

        foreach ($pages as $name => $path) {
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $name, 'item' => self::url($path)];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
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

        // Chaque question est un <details> qui n'en contient pas d'autre (« Voir les autres questions » les regroupe).
        foreach ($xpath->query('//details[not(.//details)]') as $details) {
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
