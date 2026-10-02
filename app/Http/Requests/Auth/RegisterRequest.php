<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            // Si le numéro est invalide, on garde la saisie pour la réafficher.
            'phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Ce numéro de téléphone n\'est pas valide. Exemple : 90 12 34 56 ou +228 90 12 34 56.',
            'phone.unique' => 'Un compte existe déjà avec ce numéro. Connectez-vous.',
        ];
    }
}
