<?php

namespace App\Http\Requests\Auth;

use App\Support\Countries;
use App\Support\EmailAddress;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $country = filled($this->input('country')) ? strtoupper((string) $this->input('country')) : null;

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'country' => $country,
            // Numéro local : indicatif du pays choisi. Si le numéro est invalide, on garde la saisie pour la réafficher.
            'phone' => PhoneNumber::normalize($this->input('phone'), $country) ?? $this->input('phone'),
            'email' => EmailAddress::normalize($this->input('email')),
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
            // Tous les pays du monde, ou « Autre pays » ; absent (ancien formulaire) : déduit de l'indicatif.
            'country' => ['nullable', 'string', Rule::in([...array_keys(Countries::all()), Countries::OTHER])],
            'email' => EmailAddress::rules(),
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Ce numéro de téléphone n\'est pas valide. Écrivez-le avec l\'indicatif de votre pays, par exemple +228 90 12 34 56 ou +237 6 77 12 34 56.',
            'country.in' => 'Choisissez votre pays dans la liste.',
            'phone.unique' => 'Un compte existe déjà avec ce numéro. Connectez-vous.',
        ] + EmailAddress::messages();
    }
}
