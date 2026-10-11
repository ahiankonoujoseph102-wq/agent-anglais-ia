<?php

namespace App\Support\Gemini;

/**
 * Estimation du coût d'une conversation à partir des jetons rapportés par Gemini,
 * avec les tarifs de config/services.php (en dollars US).
 */
class UsageCost
{
    public static function usd(int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens * (float) config('services.gemini.pricing.input_per_million')
            + $outputTokens * (float) config('services.gemini.pricing.output_per_million')) / 1_000_000;
    }

    /**
     * « 0,0412 $ » : quatre décimales, les montants par séance étant faibles.
     */
    public static function format(float $usd): string
    {
        return number_format($usd, 4, ',', "\u{202F}")."\u{00A0}".'$';
    }
}
