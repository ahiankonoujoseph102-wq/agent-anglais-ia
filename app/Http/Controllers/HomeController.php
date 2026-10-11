<?php

namespace App\Http\Controllers;

use App\Support\PaymentCountry;
use App\Support\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'plans' => Plan::all(),
            'operators' => PaymentCountry::operatorNames(),
            'countries' => array_map(fn (PaymentCountry $country) => $country->name, array_values(PaymentCountry::all())),
        ]);
    }
}
