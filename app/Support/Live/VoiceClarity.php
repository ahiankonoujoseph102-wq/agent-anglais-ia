<?php

namespace App\Support\Live;

use App\Models\User;
use App\Support\SiteSettings;

/**
 * « Voix plus nette » (31 octobre 2026) : MÊME voix et mêmes paroles du professeur, seul le rendu dans le
 * téléphone change (public/js/voice-clarity.js : coupe-bas, présence des consonnes, compression douce,
 * garde-fou contre la saturation). Désactivé par défaut : rien ne change pour les élèves tant que
 * l'administrateur ne l'a pas validé à l'oreille (« Réglages » → « Voix plus nette »).
 */
final class VoiceClarity
{
    public const SETTING = 'live.voice_clarity';

    /** Paramètre d'adresse de la page de séance : comparaison réservée à l'équipe (?son=net ou ?son=actuel). */
    public const QUERY = 'son';

    /** @var array<string, string> */
    public const MODES = [
        'off' => 'Désactivé (son actuel pour tout le monde)',
        'test' => 'Administrateur et comptes de test seulement',
        'all' => 'Tout le monde',
    ];

    public static function mode(): string
    {
        $value = SiteSettings::get(self::SETTING);

        return isset(self::MODES[$value]) ? $value : 'off';
    }

    /** Cet utilisateur entend-il la voix plus nette ? L'équipe peut forcer l'un ou l'autre son pour comparer. */
    public static function isClearFor(?User $user, ?string $choice = null): bool
    {
        if ($user?->isStaff() && in_array($choice, ['net', 'actuel'], true)) {
            return $choice === 'net';
        }

        return match (self::mode()) {
            'all' => true,
            'test' => (bool) ($user?->isStaff() || $user?->is_test_account),
            default => false,
        };
    }

    /** Réglages donnés au navigateur (config/platform.php → live.voice_clarity). */
    public static function clientConfig(?User $user, ?string $choice = null): array
    {
        return [
            'voice_mode' => self::isClearFor($user, $choice) ? 'clear' : 'classic',
            'voice_clear' => (array) config('platform.live.voice_clarity'),
        ];
    }
}
