<?php

namespace App\Http\Controllers;

use App\Support\CountryPage;
use App\Support\PaymentCountry;
use App\Support\Plan;
use Illuminate\View\View;

/**
 * Pages publiques du référencement : formules et prix, « À propos », pages « Apprendre l'anglais » par pays.
 */
class PageController extends Controller
{
    public function plans(): View
    {
        return view('pages.plans', [
            'plans' => Plan::all(),
            'countries' => PaymentCountry::available(),
            'countryPages' => CountryPage::all(),
        ]);
    }

    public function about(): View
    {
        return view('pages.about', ['countryPages' => CountryPage::all()]);
    }

    public function country(string $country): View
    {
        return view('pages.country', [
            'page' => CountryPage::find($country),
            'plans' => Plan::all(),
            'countryPages' => CountryPage::all(),
        ]);
    }
}
