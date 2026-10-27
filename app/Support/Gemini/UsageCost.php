<?php

namespace App\Support\Gemini;

use App\Support\Live\LiveProviders;
use Illuminate\Database\Eloquent\Model;

/**
 * Estimation du coût d'une conversation à partir des jetons rapportés par son fournisseur (Gemini Live ou
 * OpenAI Realtime), avec les tarifs de config/services.php (en dollars US).
 *
 * Chaque nature de jeton a son tarif : les consignes (texte) coûtent bien moins cher que l'audio, et OpenAI
 * facture très peu ce qui est déjà en cache. Les séances d'avant ce détail (colonnes vides), et tout jeton non
 * détaillé, restent comptés au tarif audio : l'estimation n'est jamais trop basse.
 */
class UsageCost
{
    /** Colonnes de détail des séances (même nom dans le navigateur, public/js/token-usage.js). */
    public const DETAILS = [
        'input_text_tokens', 'input_audio_tokens', 'cached_text_tokens', 'cached_audio_tokens',
        'output_text_tokens', 'output_audio_tokens',
    ];

    /**
     * Sommes SQL nécessaires au calcul du coût d'un ensemble de séances (MySQL et SQLite) : jetons, détail, et
     * reste non détaillé (séances anciennes).
     */
    public static function sumsSql(): string
    {
        $input = 'coalesce(input_text_tokens, 0) + coalesce(input_audio_tokens, 0) + coalesce(cached_text_tokens, 0) + coalesce(cached_audio_tokens, 0)';
        $output = 'coalesce(output_text_tokens, 0) + coalesce(output_audio_tokens, 0)';

        $parts = [
            'coalesce(sum(input_tokens), 0) as input',
            'coalesce(sum(output_tokens), 0) as output',
        ];

        foreach (self::DETAILS as $column) {
            $parts[] = "coalesce(sum({$column}), 0) as {$column}";
        }

        $parts[] = "coalesce(sum(case when input_tokens > {$input} then input_tokens - ({$input}) else 0 end), 0) as input_rest";
        $parts[] = "coalesce(sum(case when output_tokens > {$output} then output_tokens - ({$output}) else 0 end), 0) as output_rest";

        return implode(', ', $parts);
    }

    /**
     * Coût d'une ligne de sommes (sumsSql) ou d'une séance (ses colonnes), au tarif de son fournisseur.
     *
     * @param  object|array<string, mixed>|Model  $row
     */
    public static function fromSums(object|array $row, string $provider = LiveProviders::GEMINI): float
    {
        $value = fn (string $key): int => (int) (is_array($row) ? ($row[$key] ?? 0) : ($row->{$key} ?? 0));
        $price = self::modalityPrices($provider);

        // Séance (modèle) : le reste non détaillé se calcule ici.
        $inputRest = is_array($row) || isset($row->input_rest)
            ? $value('input_rest')
            : max(0, $value('input_tokens') - $value('input_text_tokens') - $value('input_audio_tokens') - $value('cached_text_tokens') - $value('cached_audio_tokens'));
        $outputRest = is_array($row) || isset($row->output_rest)
            ? $value('output_rest')
            : max(0, $value('output_tokens') - $value('output_text_tokens') - $value('output_audio_tokens'));

        return (
            $value('input_text_tokens') * $price['input_text']
            + ($value('input_audio_tokens') + $inputRest) * $price['input_audio']
            + $value('cached_text_tokens') * $price['cached_text']
            + $value('cached_audio_tokens') * $price['cached_audio']
            + $value('output_text_tokens') * $price['output_text']
            + ($value('output_audio_tokens') + $outputRest) * $price['output_audio']
        ) / 1_000_000;
    }

    /**
     * Coût d'une séance d'après ses colonnes.
     */
    public static function forConversation(Model $conversation): float
    {
        return self::fromSums($conversation, (string) ($conversation->getAttribute('live_provider') ?? LiveProviders::GEMINI));
    }

    /**
     * Tout au tarif audio (jetons sans détail).
     */
    public static function usd(int $inputTokens, int $outputTokens, string $provider = LiveProviders::GEMINI): float
    {
        [$input, $output] = self::prices($provider);

        return ($inputTokens * $input + $outputTokens * $output) / 1_000_000;
    }

    /**
     * Prix par million de jetons AUDIO (entrée, sortie) du fournisseur d'une séance en direct.
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
     * Prix par million de jetons, par nature.
     *
     * @return array{input_text: float, input_audio: float, cached_text: float, cached_audio: float, output_text: float, output_audio: float}
     */
    public static function modalityPrices(string $provider): array
    {
        $pricing = (array) ($provider === LiveProviders::OPENAI ? config('services.openai.realtime_pricing') : config('services.gemini.pricing'));
        [$input, $output] = self::prices($provider);

        return [
            'input_text' => (float) ($pricing['input_text_per_million'] ?? $input),
            'input_audio' => $input,
            'cached_text' => (float) ($pricing['cached_text_per_million'] ?? $pricing['input_text_per_million'] ?? $input),
            'cached_audio' => (float) ($pricing['cached_audio_per_million'] ?? $input),
            'output_text' => (float) ($pricing['output_text_per_million'] ?? $output),
            'output_audio' => $output,
        ];
    }

    /**
     * « 0,0412 $ » : quatre décimales, les montants par séance étant faibles.
     */
    public static function format(float $usd): string
    {
        return number_format($usd, 4, ',', "\u{202F}")."\u{00A0}".'$';
    }
}
