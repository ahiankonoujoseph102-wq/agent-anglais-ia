<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un événement d'une conversation orale (reconnexion, erreur du fournisseur, micro fermé ou rouvert, pause,
 * fin de tour signalée, attente d'une réponse…), affiché en petite ligne grise dans « Lire la conversation ».
 * Aucune donnée audio. Voir App\Support\Live\Transcripts.
 */
#[Fillable(['user_id', 'conversation_type', 'conversation_id', 'kind', 'detail', 'happened_at'])]
class ConversationEvent extends Model
{
    /** Types d'événement acceptés, avec leur libellé en français. */
    public const KINDS = [
        'connected' => 'Connexion au fournisseur',
        'reconnect' => 'Reconnexion',
        'provider_error' => 'Erreur ou coupure du fournisseur',
        'mic_silent' => 'Alerte « Nous ne vous entendons pas »',
        'gate_closed' => 'Micro fermé',
        'gate_opened' => 'Micro rouvert',
        'gate_safety' => 'Micro rouvert par sécurité',
        'end_of_turn' => 'Fin de parole signalée par le navigateur',
        'no_answer' => 'Aucune réponse du professeur',
        'nudge' => 'Professeur relancé par l\'apprenant',
        'auto_nudge' => 'Professeur relancé automatiquement',
        'auto_reconnect' => 'Nouvelle connexion automatique (aucune réponse)',
        'switch' => 'Séance passée chez l\'autre fournisseur (aucune réponse)',
        'interrupt' => 'Professeur interrompu',
        'pause' => 'Pause',
        'resume' => 'Reprise après la pause',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'happened_at' => 'datetime',
        ];
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
