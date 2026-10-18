<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Une synthèse vocale réussie : fournisseur, modèle, voix, morceau, durée de la voix et coût estimé.
 */
#[Fillable(['provider', 'model', 'voice', 'label', 'characters', 'audio_milliseconds', 'cost_usd'])]
class SpeechSynthesis extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'characters' => 'integer',
            'audio_milliseconds' => 'integer',
            'cost_usd' => 'float',
        ];
    }
}
