<?php

namespace App\Support;

use App\Models\User;

/**
 * Prénom de l'apprenant donné au professeur (consignes des séances) : premier mot du nom du compte, jamais un nom de
 * rôle (« Administrateur », « Admin », « Assistant »…) ni un nom vide. Sans vrai prénom, null : le professeur salue
 * sans nom et ne dit jamais « Administrateur ».
 */
class LearnerName
{
    /** Mots qui désignent un rôle ou un compte de démonstration, jamais un prénom (comparés sans accents). */
    public const NOT_A_NAME = [
        'administrateur', 'administratrice', 'admin', 'administrator', 'assistant', 'assistante', 'apprenant',
        'apprenante', 'eleve', 'utilisateur', 'utilisatrice', 'user', 'test', 'demo', 'compte', 'inconnu',
    ];

    public static function first(User $user): ?string
    {
        $first = Greeting::firstName((string) $user->name);
        $plain = mb_strtolower(str_replace(['é', 'è', 'ê', 'É'], ['e', 'e', 'e', 'e'], $first));

        return $first === '' || in_array($plain, self::NOT_A_NAME, true) ? null : $first;
    }
}
