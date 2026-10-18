<?php

namespace App\Http\Requests\Payment;

use App\Support\PaymentCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Envoi de la capture d'écran d'un paiement Mobile Money par l'apprenant.
 * Seuls les pays et opérateurs dont le numéro est saisi dans les Réglages sont acceptés.
 */
class StorePaymentRequest extends FormRequest
{
    /**
     * Hors des pays de paiement, aucune capture n'est lue ni validée : retour à la page de la formule
     * (message et bouton WhatsApp), avant toute validation.
     */
    public function validateResolved(): void
    {
        if ($this->user() !== null && ! $this->user()->canPayOnline()) {
            throw new HttpResponseException(to_route('payment.create', (string) $this->route('plan')));
        }

        parent::validateResolved();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => strtoupper((string) $this->input('country'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = PaymentCountry::findAvailable($this->input('country'));

        return [
            'country' => ['required', Rule::in(array_keys(PaymentCountry::available()))],
            'operator' => ['required', Rule::in(array_keys($country?->operators ?? []))],
            'proof' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', config('payment.proofs.mimes')),
                'max:'.(int) config('payment.proofs.max_kilobytes'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country.required' => 'Choisissez le pays depuis lequel vous payez.',
            'country.in' => 'Choisissez l\'un des pays proposés.',
            'operator.required' => 'Choisissez le moyen de paiement que vous avez utilisé.',
            'operator.in' => 'Choisissez l\'un des moyens de paiement proposés pour ce pays.',
            'proof.required' => 'Ajoutez la capture d\'écran de votre paiement.',
            'proof.image' => 'La capture doit être une image (JPEG, PNG ou WebP).',
            'proof.mimes' => 'La capture doit être une image (JPEG, PNG ou WebP).',
            'proof.max' => 'La capture est trop lourde : '.(int) (config('payment.proofs.max_kilobytes') / 1024).' mégaoctets au maximum.',
            'proof.uploaded' => 'La capture n\'a pas pu être envoyée : elle est peut-être trop lourde.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proof' => 'capture d\'écran',
        ];
    }
}
