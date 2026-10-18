<?php

namespace App\Support;

use App\Models\User;

/**
 * Liens de navigation communs aux pages publiques : l'évaluation gratuite mène à l'inscription pour un
 * visiteur, à l'évaluation (ou à sa page de résultat) pour un compte connecté.
 */
final class Navigation
{
    public static function assessmentUrl(?User $user = null): string
    {
        $user ??= auth()->user();

        return $user ? route('assessment.show') : route('register');
    }
}
