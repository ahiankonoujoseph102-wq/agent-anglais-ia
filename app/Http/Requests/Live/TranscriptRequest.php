<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un lot de répliques d'une conversation (texte seulement) : taille limitée par platform.transcripts.
 */
class TranscriptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.(int) config('platform.transcripts.max_lines_per_request')],
            'lines.*.seq' => ['required', 'integer', 'min:1', 'max:'.(int) config('platform.transcripts.max_lines_per_conversation')],
            'lines.*.speaker' => ['required', 'string', 'in:learner,teacher'],
            'lines.*.text' => ['required', 'string', 'max:'.(int) config('platform.transcripts.max_line_characters')],
            'lines.*.at' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{seq: int, speaker: string, text: string, at?: int|null}>
     */
    public function lines(): array
    {
        return array_values($this->validated('lines'));
    }
}
