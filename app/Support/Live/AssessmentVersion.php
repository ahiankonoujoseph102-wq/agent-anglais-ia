<?php

namespace App\Support\Live;

use App\Models\User;
use App\Support\SiteSettings;

/**
 * Interrupteur de la nouvelle évaluation (page « Réglages ») : « Désactivée », « Comptes de test seulement » (par
 * défaut) ou « Tout le monde ». Les prospects gardent l'évaluation actuelle tant que « Tout le monde » n'est pas choisi.
 * Un compte de test se marque dans la fiche d'un inscrit (case « Compte de test », users.is_test_account).
 */
final class AssessmentVersion
{
    public const SETTING = 'assessment.new_version';

    /** @var array<string, string> */
    public const MODES = [
        'off' => 'Désactivée',
        'test' => 'Comptes de test seulement',
        'all' => 'Tout le monde',
    ];

    /** Banc d'essai : force l'une ou l'autre version, quel que soit le réglage. */
    private static ?bool $forced = null;

    public static function mode(): string
    {
        $value = SiteSettings::get(self::SETTING);

        return isset(self::MODES[$value]) ? $value : 'test';
    }

    /** Cet apprenant passe-t-il la nouvelle évaluation ? */
    public static function isNewFor(?User $user): bool
    {
        if (self::$forced !== null) {
            return self::$forced;
        }

        return match (self::mode()) {
            'all' => true,
            'test' => (bool) $user?->is_test_account,
            default => false,
        };
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function forcing(bool $new, callable $callback): mixed
    {
        $previous = self::$forced;
        self::$forced = $new;

        try {
            return $callback();
        } finally {
            self::$forced = $previous;
        }
    }
}
