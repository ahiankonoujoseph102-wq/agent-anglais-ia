<?php

namespace App\Support\Gemini;

use App\Support\Live\LiveProviders;

/**
 * Estimation du coût d'une conversation à partir des jetons rapportés par son fournisseur (Gemini Live ou
 * OpenAI Realtime), avec les tarifs de config/services.php (en dollars US).
 */
class UsageCost
{
    public static function usd(int $inputTokens, int $outputTokens, string $provider = LiveProviders::GEMINI): float
    {
        [$input, $output] = self::prices($provider);

        return ($inputTokens * $input + $outputTokens * $output) / 1_000_000;
    }

    /**
     * Prix par million de jetons (entrée, sortie) du fournisseur d'une séance en direct.
     *
     * @return array{0: float, 1: float}
     */
    public static function prices(string $provider): array
    {
        return $provider === LiveProviders::OPENAI
            ? [(float) config('services.openai.realtime_pricing.input_per_million'), (float) config('services.openai.realtime_pricing.output_per_million')]
            : [(float) config('services.gemini.pricing.input_per_million'), (float) config('services.gemini.pricing.output_per_million')];
    }

    /**
     * « 0,0412 $ » : quatre décimales, les montants par séance étant faibles.
     */
    public static function format(float $usd): string
    {
        return number_format($usd, 4, ',', "\u{202F}")."\u{00A0}".'$';
    }
}
