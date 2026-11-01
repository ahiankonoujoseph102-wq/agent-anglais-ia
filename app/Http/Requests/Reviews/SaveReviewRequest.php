<?php

namespace App\Http\Requests\Reviews;

use App\Support\Reviews\Reviews;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Avis d'un élève : note de 1 à 5, texte court, accord pour la publication avec le prénom.
 */
class SaveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Reviews::canReview($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:'.(int) config('platform.reviews.min_characters'), 'max:'.(int) config('platform.reviews.max_characters')],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = (int) config('platform.reviews.min_characters');
        $max = (int) config('platform.reviews.max_characters');

        return [
            'rating.*' => 'Choisissez une note, de 1 à 5 étoiles.',
            'body.required' => 'Écrivez quelques mots sur votre expérience.',
            'body.min' => "Écrivez au moins {$min} caractères.",
            'body.max' => "Votre avis ne doit pas dépasser {$max} caractères.",
            'consent.accepted' => 'Cochez la case pour accepter la publication de votre avis avec votre prénom.',
        ];
    }
}
