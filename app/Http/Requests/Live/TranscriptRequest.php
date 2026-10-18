<?php

namespace App\Http\Requests\Live;

use App\Models\ConversationEvent;
use App\Support\Live\Transcripts;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un lot de répliques d'une conversation (texte seulement, avec l'heure réelle de la parole et les mesures du tour)
 * et d'événements de la séance : taille limitée par platform.transcripts.
 */
class TranscriptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required_without:events', 'array', 'max:'.(int) config('platform.transcripts.max_lines_per_request')],
            'lines.*.seq' => ['required', 'integer', 'min:1', 'max:'.(int) config('platform.transcripts.max_lines_per_conversation')],
            'lines.*.speaker' => ['required', 'string', 'in:learner,teacher'],
            'lines.*.text' => ['required', 'string', 'max:'.(int) config('platform.transcripts.max_line_characters')],
            'lines.*.at' => ['nullable', 'integer', 'min:0'],
            'lines.*.end' => ['nullable', 'integer', 'min:0'],
            // Mesures du tour : seulement les clés connues, des nombres entiers (heures en millisecondes, délai).
            'lines.*.metrics' => ['nullable', 'array:'.implode(',', Transcripts::METRICS)],
            'lines.*.metrics.*' => ['nullable', 'integer', 'min:0'],
            'events' => ['required_without:lines', 'array', 'max:'.(int) config('platform.transcripts.max_events_per_request')],
            'events.*.kind' => ['required', 'string', 'in:'.implode(',', array_keys(ConversationEvent::KINDS))],
            'events.*.detail' => ['nullable', 'string', 'max:200'],
            'events.*.at' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{seq: int, speaker: string, text: string, at?: int|null, end?: int|null, metrics?: array<string, int>|null}>
     */
    public function lines(): array
    {
        return array_values($this->validated('lines') ?? []);
    }

    /**
     * @return list<array{kind: string, detail?: string|null, at?: int|null}>
     */
    public function events(): array
    {
        return array_values($this->validated('events') ?? []);
    }
}
