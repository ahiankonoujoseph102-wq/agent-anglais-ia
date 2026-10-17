<?php

namespace App\Http\Controllers;

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
        $operators = [];

        foreach ($countries as $country) {
            foreach ($country->operators as $operator) {
                $operators[] = $operator->name;
            }
        }

        $whatsapp = SiteSettings::whatsapp();

        return view('home', [
            'plans' => Plan::all(),
            'operators' => array_values(array_unique($operators)),
            'countries' => array_map(fn (PaymentCountry $country) => $country->name, array_values($countries)),
            'whatsappUrl' => $whatsapp ? WhatsApp::url($whatsapp, 'Bonjour '.config('platform.name').', j\'ai une question : ') : null,
        ]);
    }
}
