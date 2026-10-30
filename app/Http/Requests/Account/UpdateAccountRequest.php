<?php

namespace App\Http\Requests\Account;

use App\Support\EmailAddress;
use App\Support\Reminders\LessonReminders;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => EmailAddress::normalize($this->input('email'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = EmailAddress::rules($this->user()->id);

        // Abonné payant : l'adresse est obligatoire (rappels de séance), elle ne peut plus être retirée.
        if (LessonReminders::follows($this->user())) {
            $rules[0] = 'required';
        }

        return ['email' => $rules];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return EmailAddress::messages() + [
            'email.required' => 'Ajoutez votre adresse e-mail : elle est nécessaire pour recevoir vos rappels de séance.',
        ];
    }
}
