<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    /**
     * Un sac d'erreurs par paiement : la page en affiche plusieurs à la fois.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'reject-'.$this->route('payment')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Indiquez le motif du refus : il sera affiché à l\'apprenant.',
        ];
    }
}
