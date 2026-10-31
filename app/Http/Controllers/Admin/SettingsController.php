<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Live\AssessmentVersion;
use App\Support\Live\CostWatch;
use App\Support\Live\VoiceClarity;
use App\Support\PaymentCountry;
use App\Support\SiteSettings;
use App\Support\SubscribersClub;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * « Réglages » : numéros de réception Mobile Money (et titulaires), coordonnées du centre, seuil de l'alerte de coût,
 * nombre de places d'ambassadeurs.
 * Stockés en base (table settings) ; ils remplacent les valeurs par défaut de la
 * configuration. Un champ vide revient à la valeur par défaut, affichée sous le champ.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $keys = [...SiteSettings::CONTACT_KEYS, ...CostWatch::SETTINGS, AmbassadorProgram::PLACES_SETTING, SubscribersClub::SETTING];

        foreach (PaymentCountry::all() as $country) {
            foreach ($country->operators as $operator) {
                $keys[] = SiteSettings::operatorNumberKey($country->code, $operator->key);
                $keys[] = SiteSettings::operatorHolderKey($country->code, $operator->key);
            }
        }

        $fields = [];

        foreach ($keys as $key) {
            $fields[$key] = ['saved' => SiteSettings::saved($key), 'default' => SiteSettings::default($key)];
        }

        return view('admin.settings.edit', [
            'countries' => PaymentCountry::all(),
            'fields' => $fields,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $values = [
            'contact.address' => $request->input('contact.address'),
            'contact.phones' => $request->input('contact.phones'),
            'contact.email' => $request->input('contact.email'),
            'contact.whatsapp' => $request->input('contact.whatsapp'),
            'cost_alert.percent' => $request->input('cost_alert.percent'),
            'cost_alert.xof_per_usd' => $request->input('cost_alert.xof_per_usd'),
            AmbassadorProgram::PLACES_SETTING => $request->input('ambassadors.places'),
        ];

        // Lien du Club des abonnés : gardé seulement s'il est envoyé (vide : l'encadré n'est plus montré).
        if ($request->has('club')) {
            $values[SubscribersClub::SETTING] = $request->input('club.whatsapp_link');
        }

        // Interrupteur de la nouvelle évaluation : gardé seulement s'il est envoyé (ancien formulaire : inchangé).
        if ($request->filled('assessment.new_version')) {
            $values[AssessmentVersion::SETTING] = $request->input('assessment.new_version');
        }

        // « Voix plus nette » : gardé seulement s'il est envoyé (ancien formulaire : inchangé).
        if ($request->filled('voice.clarity')) {
            $values[VoiceClarity::SETTING] = $request->input('voice.clarity');
        }

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
            : 'Réglages enregistrés. Attention : aucun numéro de paiement, les apprenants ne peuvent pas payer.');
    }
}
