<?php

namespace Tests;

use App\Support\Typography;
use Illuminate\Testing\TestResponse;

/**
 * Réponse de test qui compare le texte avec des espaces ordinaires : les espaces insécables ajoutés par
 * App\Support\Typography (devant « ? ! ; : » et dans les guillemets) sont remis en espaces simples, pour que
 * les tests écrivent « Mes cours » comme dans le code. rawContent() rend la page telle qu'elle est envoyée
 * (vérifiée par tests/Feature/TypographyTest.php). Les autres espaces insécables (prix, durées) ne changent pas.
 */
class PlainSpacesResponse extends TestResponse
{
    public function getContent(): string|false
    {
        $content = $this->baseResponse->getContent();

        return is_string($content) ? self::plain($content) : $content;
    }

    public function rawContent(): string|false
    {
        return $this->baseResponse->getContent();
    }

    public static function plain(string $content): string
    {
        // Fichiers (PDF, WAV…) : jamais touchés.
        if (! mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $content = (string) preg_replace_callback(
            '/«'.Typography::NBSP.'([^«»\r\n]{1,'.Typography::SHORT_QUOTE.'}?)'.Typography::NBSP.'»/u',
            fn (array $match) => '«'.Typography::NBSP.str_replace(Typography::NBSP, ' ', $match[1]).Typography::NBSP.'»',
            $content,
        );

        return str_replace(
            [Typography::THIN.'?', Typography::THIN.'!', Typography::THIN.';', Typography::NBSP.':', '«'.Typography::NBSP, Typography::NBSP.'»'],
            [' ?', ' !', ' ;', ' :', '« ', ' »'],
            $content,
        );
    }
}
