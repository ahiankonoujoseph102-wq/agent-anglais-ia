<?php

namespace App\Support;

/**
 * Adresse e-mail facultative de l'apprenant : sans espaces, en minuscules, null si vide.
 */
class EmailAddress
{
    /** Texte d'aide sous le champ (inscription et compte). */
    public const HINT = 'Facultatif. Pour recevoir vos documents de révision par e-mail.';

    /** Texte d'aide pour un abonné payant : l'adresse est obligatoire (rappels de séance). */
    public const REQUIRED_HINT = 'Obligatoire pour les abonnés : pour recevoir vos rappels de séance et vos documents de révision.';

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
