<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Une réponse du modèle texte (capture, leçon, estimation, rédaction) : fournisseur, modèle, usage, jetons
 * et coût estimé.
 */
#[Fillable(['provider', 'model', 'kind', 'input_tokens', 'output_tokens', 'duration_ms', 'cost_usd'])]
class TextAnalysis extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
            'cost_usd' => 'float',
        ];
    }
}
