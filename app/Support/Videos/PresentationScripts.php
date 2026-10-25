<?php

namespace App\Support\Videos;

use App\Enums\Level;
use App\Models\Assessment;
use App\Support\Curriculum\Curriculum;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\SiteSettings;

/**
 * Scripts des vidéos de présentation (resources/videos/presentations.php) : repères remplis depuis la configuration
 * (formules, prix, durées, noms, adresse du site), texte dit (nombres en toutes lettres, adresse « … point … ») et
 * sous-titre (nombres en chiffres, *mots* en orange : [en]…[/en] pour tools/video/subtitles.mjs).
 */
class PresentationScripts
{
    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return require resource_path('videos/presentations.php');
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Script prêt pour la voix et la fabrication, ou null si la vidéo n'existe pas ou si l'adresse du site manque.
     *
     * @return array{title: string, caption: string, hashtags: list<string>, segments: list<array<string, mixed>>}|null
     */
    public static function script(string $key, ?string $site = null): ?array
    {
        $definition = self::definitions()[$key] ?? null;
        $site ??= VideoSettings::site();

        if ($definition === null || $site === null) {
            return null;
        }

        $segments = [];

        foreach ($definition['segments'] as $segment) {
            $segments[] = [
                'spoken' => self::render($segment['text'], true, $site),
                'subtitle' => self::render($segment['text'], false, $site),
                'shot' => $segment['shot'] === 'full' ? 'full' : 'screen',
                'scene' => (string) $segment['scene'],
                'chapter' => isset($segment['chapter']) ? self::render($segment['chapter'], false, $site) : null,
                'medallion' => (bool) ($segment['medallion'] ?? true),
                'closing' => (bool) ($segment['closing'] ?? false),
                // Bandeau : *mots* en orange ([en]…[/en]), comme les sous-titres.
                'card' => array_map(fn (string $line) => self::render($line, false, $site), $segment['card'] ?? []),
                'sfx' => in_array($segment['sfx'] ?? null, self::SFX, true) ? $segment['sfx'] : null,
                'pause_after' => max(0, min(5000, (int) ($segment['pause_after'] ?? 0))),
            ];
        }

        return [
            'title' => self::render($definition['title'], false, $site, plain: true),
            'caption' => self::render($definition['caption'], false, $site, plain: true),
            'hashtags' => array_values($definition['hashtags']),
            'voice_style' => $definition['voice_style'] ?? null,
            'layout' => ($definition['layout'] ?? null) === 'ad' ? 'ad' : null,
            'segments' => $segments,
        ];
    }

    /** Bruitages possibles au début d'une phrase (fabriqués par ffmpeg, aucune licence : tools/presentation/compose.mjs). */
    public const SFX = ['pop', 'ding'];

    /**
     * Empreinte de ce qui est dit (un texte changé refait la voix) ; les silences ajoutés (« pause_after ») en font
     * partie seulement s'il y en a : les vidéos sans silence gardent leur empreinte.
     */
    public static function hash(array $script): string
    {
        $spoken = array_column($script['segments'], 'spoken');
        $pauses = array_map(fn (array $segment) => (int) ($segment['pause_after'] ?? 0), $script['segments']);

        return hash('sha256', json_encode(array_sum($pauses) > 0 ? [$spoken, $pauses] : $spoken));
    }

    /**
     * Texte dit (nombres en lettres, aucune balise) ou sous-titre (chiffres, *mots* en [en]…[/en], ou sans balise si
     * « plain »).
     */
    public static function render(string $text, bool $spoken, string $site, bool $plain = false): string
    {
        // {{dit|écrit}} : « dix-neuf heures » dans la voix, « 19 heures » à l'écran.
        $text = preg_replace_callback('/\{\{([^|}]*)\|([^}]*)\}\}/u', fn (array $m) => $spoken ? $m[1] : $m[2], $text) ?? $text;
        $text = strtr($text, self::markers($spoken, $site));

        if ($spoken || $plain) {
            return trim(preg_replace('/\s+/u', ' ', str_replace('*', '', $text)) ?? $text);
        }

        return trim(preg_replace('/\*([^*]+)\*/u', '[en]$1[/en]', $text) ?? $text);
    }

    /**
     * Repères et leurs valeurs : dits (en lettres) ou écrits (en chiffres).
     *
     * @return array<string, string>
     */
    public static function markers(bool $spoken, string $site): array
    {
        $number = fn (int $value) => $spoken ? self::words($value) : (string) $value;
        $markers = [
            ':teacher' => (string) config('avatar.model.name'),
            ':platform' => (string) config('platform.name'),
            ':site' => $spoken ? str_replace('.', ' point ', $site) : $site,
            ':assessment_minutes' => $number((int) config('platform.assessment.max_minutes')),
            ':assessment_total' => $number(Assessment::totalMinutes()),
            ':questions_max' => $number((int) config('platform.assessment.questions_max')),
            ':warning_minutes' => $number((int) config('platform.lessons.warning_minutes_before_end')),
            ':course_minutes' => $number((int) config('platform.courses.max_session_minutes')),
            ':course_photos' => $number((int) config('platform.courses.max_photos')),
            ':pdf_pages' => $number((int) config('platform.courses.pdf_max_pages')),
            ':part_pages' => $number((int) config('platform.courses.part_max_pages')),
            ':units' => $number(count(Curriculum::units(Level::A1))),
            ':address' => (string) (SiteSettings::contact()['address'] ?? ''),
            ':pay_countries' => self::countries(),
            ':group_link' => (string) (config('platform.contact.whatsapp_group') ?: '[collez ici le lien du groupe]'),
        ];

        foreach (Plan::all() as $plan) {
            $markers[':name_'.$plan->key] = $plan->name;
            $markers[':days_'.$plan->key] = $number($plan->durationDays);
            $markers[':minutes_'.$plan->key] = $number($plan->dailyMinutes);
            $markers[':price_'.$plan->key] = $spoken
                ? self::words($plan->price).' francs CFA'
                : number_format($plan->price, 0, ',', "\u{00A0}")."\u{00A0}".config('platform.currency');
        }

        return $markers;
    }

    /** Pays où l'on paie en ligne : « au Togo, au Burkina Faso, au Bénin et en Côte d'Ivoire » (configuration). */
    public static function countries(): string
    {
        $names = array_map(
            fn (PaymentCountry $country) => (str_starts_with($country->name, 'Côte') ? 'en ' : 'au ').$country->name,
            array_values(PaymentCountry::all()),
        );
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' et '.$last;
    }

    /** Nombre entier en toutes lettres, en français (0 à 999 999). */
    public static function words(int $number): string
    {
        if ($number === 0) {
            return 'zéro';
        }

        if ($number >= 1000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;
            $head = $thousands === 1 ? 'mille' : self::belowThousand($thousands, false).' mille';

            return $rest === 0 ? $head : $head.' '.self::belowThousand($rest, true);
        }

        return self::belowThousand($number, true);
    }

    /** 1 à 999 ; « cents » et « quatre-vingts » prennent un s seulement en fin de nombre. */
    private static function belowThousand(int $number, bool $last): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $parts = [];

        if ($hundreds > 0) {
            $parts[] = $hundreds === 1 ? 'cent' : self::belowHundred($hundreds, false).' cent'.($rest === 0 && $last ? 's' : '');
        }

        if ($rest > 0) {
            $parts[] = self::belowHundred($rest, $last);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $number, bool $last): string
    {
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

        if ($number < 20) {
            return $units[$number];
        }

        $ten = intdiv($number, 10);
        $unit = $number % 10;

        if ($ten === 7 || $ten === 9) {
            $base = $ten === 7 ? 'soixante' : 'quatre-vingt';
            $tail = $units[10 + $unit];

            return $base.($ten === 7 && $unit === 1 ? ' et ' : '-').$tail;
        }

        if ($ten === 8) {
            return $unit === 0 ? 'quatre-vingt'.($last ? 's' : '') : 'quatre-vingt-'.$units[$unit];
        }

        if ($unit === 0) {
            return $tens[$ten];
        }

        return $tens[$ten].($unit === 1 ? ' et un' : '-'.$units[$unit]);
    }
}
