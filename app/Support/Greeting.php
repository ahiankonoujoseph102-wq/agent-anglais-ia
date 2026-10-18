<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Salutation de Teacher Joe : « Bonjour » ou « Bonsoir » selon l'heure du pays de l'apprenant
 * (déduit de l'indicatif de son téléphone ; fuseaux dans config/payment.php, heures de bascule
 * dans config/platform.php → welcome).
 */
final class Greeting
{
    public const MORNING = 'morning';

    public const EVENING = 'evening';

    /** @var array<string, array{fr: string, en: string}> */
    private const WORDS = [
        self::MORNING => ['fr' => 'Bonjour', 'en' => 'Good morning'],
        self::EVENING => ['fr' => 'Bonsoir', 'en' => 'Good evening'],
    ];

    /**
     * Fuseau horaire du pays de l'apprenant (celui du site si l'indicatif est inconnu).
     */
    public static function timezone(?string $phone): string
    {
        $digits = ltrim((string) $phone, '+');

        foreach (config('payment.countries', []) as $country) {
            $dial = (string) ($country['dial_code'] ?? '');

            if ($dial !== '' && str_starts_with($digits, $dial) && filled($country['timezone'] ?? null)) {
                return (string) $country['timezone'];
            }
        }

        return (string) config('app.timezone');
    }

    /**
     * « morning » de morning_from à evening_from (exclu), « evening » le reste du temps (nuit comprise).
     */
    public static function period(User $user, ?CarbonInterface $now = null): string
    {
        $hour = Carbon::instance($now ?? now())->setTimezone(self::timezone($user->phone))->hour;

        return $hour >= (int) config('platform.welcome.morning_from') && $hour < (int) config('platform.welcome.evening_from')
            ? self::MORNING
            : self::EVENING;
    }

    /**
     * @return array{fr: string, en: string}
     */
    public static function words(string $period): array
    {
        return self::WORDS[$period] ?? self::WORDS[self::EVENING];
    }

    public static function isPeriod(string $value): bool
    {
        return isset(self::WORDS[$value]);
    }

    /**
     * Prénom : premier mot du nom saisi à l'inscription, nettoyé (lettres, tirets, apostrophes), première lettre
     * en majuscule. Jamais plus de platform.welcome.name_max_length caractères.
     */
    public static function firstName(string $name): string
    {
        $first = Str::before(trim((string) preg_replace('/\s+/u', ' ', $name)).' ', ' ');
        $first = (string) preg_replace('/[^\p{L}\-\']/u', '', $first);
        $first = mb_substr($first, 0, (int) config('platform.welcome.name_max_length'));

        return mb_convert_case($first, MB_CASE_TITLE, 'UTF-8');
    }
}
