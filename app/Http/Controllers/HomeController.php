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
            'countryPages' => CountryPage::all(),
            // Opérateurs pays par pays (étiquettes sous les formules, question fréquente sur le paiement).
            'paymentCountries' => array_map(fn (PaymentCountry $country) => [
                'name' => $country->name,
                'operators' => array_map(fn ($operator) => $operator->name, array_values($country->operators)),
            ], array_values($countries)),
            'whatsappUrl' => $whatsapp ? WhatsApp::url($whatsapp, 'Bonjour '.config('platform.name').', j\'ai une question : ') : null,
        ]);
    }
}
