<?php

namespace App\Support;

/**
 * Adresse e-mail facultative de l'apprenant : sans espaces, en minuscules, null si vide.
 */
class EmailAddress
{
    /** Texte d'aide sous le champ (inscription et compte). */
    public const HINT = 'Facultatif, pour vous contacter si besoin.';

    public static function normalize(mixed $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Règles de validation communes (inscription et compte).
     *
     * @return array<int, mixed>
     */
    public static function rules(?int $ignoreUserId = null): array
    {
        $unique = 'unique:users,email'.($ignoreUserId !== null ? ','.$ignoreUserId : '');

        return ['nullable', 'string', 'max:255', 'email', $unique];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.email' => 'Cette adresse e-mail n\'est pas valide. Exemple : prenom.nom@exemple.com.',
            'email.max' => 'Cette adresse e-mail est trop longue.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ];
    }
}
