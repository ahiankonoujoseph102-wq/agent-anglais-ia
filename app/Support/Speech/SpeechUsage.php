<?php

namespace App\Support\Speech;

use App\Models\SpeechSynthesis;

/**
 * Consommation de la synthèse vocale : chaque réussite est enregistrée avec son fournisseur, la durée de la
 * voix (lue dans l'en-tête WAV) et un coût estimé. Un échec d'enregistrement ne gêne jamais la synthèse.
 */
final class SpeechUsage
{
    public static function record(SpeechProvider $provider, string $label, string $text, string $wav, ?string $voice = null): void
    {
        $milliseconds = self::milliseconds($wav);

        try {
            SpeechSynthesis::query()->create([
                'provider' => $provider->name(),
                'model' => mb_substr($provider->model(), 0, 80),
                'voice' => mb_substr($voice ?? $provider->voice(), 0, 40),
                'label' => mb_substr($label, 0, 160),
                'characters' => mb_strlen($text),
                'audio_milliseconds' => $milliseconds,
                'cost_usd' => round($milliseconds / 60000 * $provider->costPerMinute(), 6),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Durée d'un fichier WAV en millisecondes (taille des données / octets par seconde), 0 si illisible.
     */
    public static function milliseconds(string $wav): int
    {
        if (strlen($wav) < 44 || ! str_starts_with($wav, 'RIFF')) {
            return 0;
        }

        $byteRate = unpack('V', substr($wav, 28, 4))[1];
        $data = strpos($wav, 'data', 12);

        if ($byteRate <= 0 || $data === false) {
            return 0;
        }

        $length = min(unpack('V', substr($wav, $data + 4, 4))[1], strlen($wav) - $data - 8);

        return (int) round(max(0, $length) / $byteRate * 1000);
    }

    /**
     * Totaux par fournisseur : nombre, caractères, durée de la voix, coût estimé.
     *
     * @return array<string, array{count: int, characters: int, milliseconds: int, cost: float}>
     */
    public static function totals(): array
    {
        $rows = SpeechSynthesis::query()->toBase()
            ->selectRaw('provider, count(*) as aggregate_count, coalesce(sum(characters), 0) as characters, coalesce(sum(audio_milliseconds), 0) as milliseconds, coalesce(sum(cost_usd), 0) as cost')
            ->groupBy('provider')
            ->get();
        $totals = [];

        foreach (SpeechProviders::all() as $provider) {
            $row = $rows->firstWhere('provider', $provider->name());
            $totals[$provider->name()] = [
                'count' => (int) ($row->aggregate_count ?? 0),
                'characters' => (int) ($row->characters ?? 0),
                'milliseconds' => (int) ($row->milliseconds ?? 0),
                'cost' => (float) ($row->cost ?? 0),
            ];
        }

        return $totals;
    }
}
