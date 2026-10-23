<?php

namespace App\Http\Controllers;

use App\Support\CountryPage;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\SiteSettings;
use App\Support\WhatsApp;
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
            'whatsappUrl' => $whatsapp ? WhatsApp::url($whatsapp, 'Bonjour '.config('platform.name').', j\'ai une question : ') : null,
        ]);
    }
}
