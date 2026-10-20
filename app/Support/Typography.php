<?php

namespace App\Support;

/**
 * Typographie française des pages : espace insécable devant « ? ! ; : » et à l'intérieur des guillemets,
 * pour qu'un signe ne parte jamais seul à la ligne ; un texte court entre guillemets (« Mes cours ») reste
 * entier sur une ligne.
 *
 * Seul le TEXTE est touché : jamais les balises et leurs attributs, ni le contenu de <script>, <style>,
 * <textarea> et <pre>. Espace fine insécable (U+202F) devant ? ! ;, insécable (U+00A0) devant : et dans
 * les guillemets.
 */
class Typography
{
    public const THIN = "\u{202F}";

    public const NBSP = "\u{00A0}";

    private const PROTECTED = '~(<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<pre\b.*?</pre\s*>|<!--.*?-->|<[^>]*>)~is';

    public static function html(string $html): string
    {
        if (! mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        $parts = preg_split(self::PROTECTED, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            // Les morceaux impairs sont les balises et les blocs protégés.
            if ($index % 2 === 0 && $part !== '') {
                $parts[$index] = self::text($part);
            }
        }

        return implode('', $parts);
    }

    /**
     * Guillemets courts (« Mes cours », « Aide ») : gardés entiers sur une ligne.
     */
    public const SHORT_QUOTE = 24;

    public static function text(string $text): string
    {
        $text = (string) preg_replace(
            ['/[ \t]+([?!;])/u', '/[ \t]+:(?!\/\/)/u', '/«[ \t\r\n]+/u', '/[ \t\r\n]+»/u'],
            [self::THIN.'$1', self::NBSP.':', '«'.self::NBSP, self::NBSP.'»'],
            $text,
        );

        return (string) preg_replace_callback(
            '/«'.self::NBSP.'([^«»\r\n]{1,'.self::SHORT_QUOTE.'}?)'.self::NBSP.'»/u',
            fn (array $match) => '«'.self::NBSP.str_replace(' ', self::NBSP, $match[1]).self::NBSP.'»',
            $text,
        );
    }
}
