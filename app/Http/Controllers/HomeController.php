<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\CountryPage;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\Reviews\Reviews;
use App\Support\SiteSettings;
use App\Support\WhatsApp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Pays et opérateurs réellement proposés (numéro par défaut ou saisi dans les Réglages).
        $countries = PaymentCountry::available();
        $whatsapp = SiteSettings::whatsapp();

        return view('home', [
            'plans' => Plan::all(),
            // Pays de paiement (un lien vers la page de chaque pays) et moyens de paiement réellement proposés
            // (numéro par défaut ou saisi dans les Réglages), affichés une seule fois, sous les formules.
            'paymentCountries' => array_values(array_map(fn (CountryPage $page) => [
                'slug' => $page->slug,
                'name' => $page->name,
                'operators' => array_map(fn ($operator) => $operator->name, array_values(($countries[$page->code] ?? null)?->operators ?? [])),
            ], CountryPage::all())),
            // Avis des élèves : seulement à partir de quelques avis publiés (aucun avis inventé, jamais un bloc vide).
            ...self::reviews(),
            'whatsappUrl' => $whatsapp ? WhatsApp::url($whatsapp, 'Bonjour '.config('platform.name').', j\'ai une question : ') : null,
        ]);
    }

    /**
     * Avis de l'accueil ; base indisponible : aucun avis plutôt qu'une page d'erreur (l'accueil reste la porte d'entrée).
     *
     * @return array{homeReviews: Collection<int, Review>, reviewStats: array{count: int, average: float|null}}
     */
    private static function reviews(): array
    {
        try {
            return ['homeReviews' => Reviews::forHome(), 'reviewStats' => Reviews::stats()];
        } catch (QueryException) {
            return ['homeReviews' => collect(), 'reviewStats' => ['count' => 0, 'average' => null]];
        }
    }
}
