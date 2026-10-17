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
            // Facultatif : sans motif, l'apprenant est invité à écrire sur WhatsApp.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
