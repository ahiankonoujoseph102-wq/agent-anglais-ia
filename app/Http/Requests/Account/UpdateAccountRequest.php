<?php

namespace App\Http\Requests\Account;

use App\Support\EmailAddress;
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
        return [
            'email' => EmailAddress::rules($this->user()->id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return EmailAddress::messages();
    }
}
