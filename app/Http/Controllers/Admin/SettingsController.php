<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Support\PaymentCountry;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * « Réglages » : numéros de réception Mobile Money (et titulaires), coordonnées du centre.
 * Stockés en base (table settings) : aucun numéro dans le dépôt, qui est public.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'countries' => PaymentCountry::all(),
            'contact' => SiteSettings::contact(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $values = [
            'contact.address' => $request->input('contact.address'),
            'contact.phones' => $request->input('contact.phones'),
            'contact.email' => $request->input('contact.email'),
            'contact.whatsapp' => $request->input('contact.whatsapp'),
        ];

        foreach (PaymentCountry::all() as $country) {
            foreach ($country->operators as $operator) {
                $values[SiteSettings::operatorNumberKey($country->code, $operator->key)] = $request->input("operators.{$country->code}.{$operator->key}.number");
                $values[SiteSettings::operatorHolderKey($country->code, $operator->key)] = $request->input("operators.{$country->code}.{$operator->key}.holder");
            }
        }

        SiteSettings::save($values);

        $available = count(PaymentCountry::available());

        return to_route('admin.settings.edit')->with('status', $available > 0
            ? 'Réglages enregistrés.'
            : 'Réglages enregistrés. Attention : aucun numéro de paiement n\'est saisi, les apprenants ne peuvent pas payer.');
    }
}
