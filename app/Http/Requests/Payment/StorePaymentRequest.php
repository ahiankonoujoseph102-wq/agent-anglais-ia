<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use App\Support\PaymentCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Déclaration d'un paiement Mobile Money par l'apprenant.
 */
class StorePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) $this->input('country')),
            'payer_phone' => trim((string) preg_replace('/[^\d+]+/', ' ', (string) $this->input('payer_phone'))),
            'reference' => trim((string) $this->input('reference')),
            'reference_key' => Payment::referenceKey((string) $this->input('reference')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = PaymentCountry::find($this->input('country'));

        return [
            'country' => ['required', Rule::in(array_keys(PaymentCountry::all()))],
            'operator' => ['required', Rule::in(array_keys($country?->operators ?? []))],
            'payer_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d ]{8,20}$/'],
            'reference' => ['required', 'string', 'min:4', 'max:100'],
            // Une même référence ne peut pas être envoyée deux fois (sauf après un refus).
            'reference_key' => ['required', 'string', 'min:4', Rule::unique('payments', 'reference_key')],
            'proof' => [
                'required',
                'file',
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
            'operator.required' => 'Choisissez l\'opérateur avec lequel vous avez payé.',
            'operator.in' => 'Choisissez l\'un des opérateurs proposés pour ce pays.',
            'payer_phone.regex' => 'Indiquez le numéro qui a payé, par exemple +228 90 12 34 56.',
            'reference_key.unique' => 'Cette référence de transaction a déjà été envoyée.',
            'reference_key.min' => 'Cette référence de transaction est trop courte.',
            'proof.required' => 'Ajoutez la capture d\'écran de votre paiement.',
            'proof.mimes' => 'La capture doit être une image (JPG, PNG ou WebP).',
            'proof.max' => 'La capture est trop lourde : '.(int) (config('payment.proofs.max_kilobytes') / 1024).' Mo au maximum.',
            'proof.uploaded' => 'La capture n\'a pas pu être envoyée : elle est peut-être trop lourde.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payer_phone' => 'numéro qui a payé',
            'reference' => 'référence de la transaction',
            'reference_key' => 'référence de la transaction',
            'proof' => 'capture d\'écran',
        ];
    }
}
