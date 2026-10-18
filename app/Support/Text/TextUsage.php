<?php

namespace App\Support\Text;

use App\Models\TextAnalysis;

/**
 * Consommation du modèle texte : chaque réponse est enregistrée avec son fournisseur, son usage, ses jetons
 * et un coût estimé. Un échec d'enregistrement ne gêne jamais la demande.
 */
final class TextUsage
{
    public static function record(TextResult $result, TextRequest $request): void
    {
        $provider = TextProviders::find($result->provider);
        $cost = $provider === null ? 0.0
            : ($result->inputTokens * $provider->inputPrice() + $result->outputTokens * $provider->outputPrice()) / 1_000_000;

        try {
            TextAnalysis::query()->create([
                'provider' => $result->provider,
                'model' => mb_substr($result->model, 0, 80),
                'kind' => mb_substr($request->kind, 0, 30),
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'duration_ms' => $result->durationMs,
                'cost_usd' => round($cost, 6),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Totaux par fournisseur : nombre de demandes, jetons, coût estimé.
     *
     * @return array<string, array{count: int, input_tokens: int, output_tokens: int, cost: float}>
     */
    public static function totals(): array
    {
        $rows = TextAnalysis::query()->toBase()
            ->selectRaw('provider, count(*) as aggregate_count, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens, coalesce(sum(cost_usd), 0) as cost')
            ->groupBy('provider')
            ->get();
        $totals = [];

        foreach (TextProviders::all() as $provider) {
            $row = $rows->firstWhere('provider', $provider->name());
            $totals[$provider->name()] = [
                'count' => (int) ($row->aggregate_count ?? 0),
                'input_tokens' => (int) ($row->input_tokens ?? 0),
                'output_tokens' => (int) ($row->output_tokens ?? 0),
                'cost' => (float) ($row->cost ?? 0),
            ];
        }

        return $totals;
    }

    /**
     * Nombre de demandes par usage et par fournisseur.
     *
     * @return array<string, array<string, int>>
     */
    public static function byKind(): array
    {
        $counts = [];

        foreach (TextAnalysis::query()->toBase()->selectRaw('kind, provider, count(*) as aggregate_count')->groupBy('kind', 'provider')->get() as $row) {
            $counts[$row->kind][$row->provider] = (int) $row->aggregate_count;
        }

        return $counts;
    }
}
