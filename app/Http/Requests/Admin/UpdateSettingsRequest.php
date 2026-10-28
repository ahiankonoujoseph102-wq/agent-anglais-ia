<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réglages de l'administration : numéros Mobile Money, coordonnées du centre et seuil de l'alerte de coût.
 */
class UpdateSettingsRequest extends FormRequest
{
    private const PHONE = 'regex:/^\+?[\d .-]{8,20}$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'contact.address' => ['nullable', 'string', 'max:200'],
            'contact.phones' => ['nullable', 'string', 'max:300'],
            'contact.email' => ['nullable', 'email', 'max:150'],
            'contact.whatsapp' => ['nullable', 'string', 'max:25', self::PHONE],
            // Alerte de coût par abonné : pourcentage du prix de la formule, francs CFA pour un dollar US.
            'cost_alert.percent' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'cost_alert.xof_per_usd' => ['nullable', 'integer', 'min:100', 'max:5000'],
            'ambassadors.places' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];

        foreach (config('payment.countries') as $code => $country) {
            foreach (array_keys($country['operators']) as $operator) {
                $rules["operators.{$code}.{$operator}.number"] = ['nullable', 'string', 'max:25', self::PHONE];
                $rules["operators.{$code}.{$operator}.holder"] = ['nullable', 'string', 'max:100'];
            }
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        // Chaque numéro de contact (un par ligne) doit ressembler à un numéro.
        $validator->after(function ($validator) {
            foreach (preg_split('/[\r\n,;]+/', (string) $this->input('contact.phones')) ?: [] as $phone) {
                if (trim($phone) !== '' && ! preg_match('/^\+?[\d .-]{8,20}$/', trim($phone))) {
                    $validator->errors()->add('contact.phones', 'Un des numéros de contact n\'est pas valide : '.trim($phone).'.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact.whatsapp.regex' => 'Le numéro WhatsApp n\'est pas valide. Exemple : +228 90 00 00 00.',
            'operators.*.*.number.regex' => 'Ce numéro n\'est pas valide. Exemple : +228 90 00 00 00.',
            'contact.email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'cost_alert.percent.*' => 'Indiquez un pourcentage entier entre 1 et 1 000.',
            'cost_alert.xof_per_usd.*' => 'Indiquez un nombre entier de francs CFA entre 100 et 5 000.',
            'ambassadors.places.*' => 'Indiquez un nombre entier de places entre 0 et 500.',
        ];
    }
}
