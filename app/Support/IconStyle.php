<?php

namespace App\Support;

/**
 * Style des pastilles d'icône (config platform.brand.icon_style), posé sur le <body>
 * (data-icon-style). Les couleurs de chaque style sont dans public/css/tokens.css.
 */
class IconStyle
{
    public const DEFAULT = 'night';

    /**
     * Styles proposés : clé => nom affiché sur la page « Aperçu des icônes ».
     */
    public const STYLES = [
        'night' => 'Bleu nuit en relief, icône orange',
        'gradient' => 'Dégradé orange, icône blanche',
        'duotone' => 'Duotone : fond blanc, icône bleu nuit et orange clair',
    ];

    public static function current(): string
    {
        $style = (string) config('platform.brand.icon_style');

        return array_key_exists($style, self::STYLES) ? $style : self::DEFAULT;
    }
}
