<?php

namespace App\Http\Requests\Live;

use App\Enums\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentResultRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(Level::class)],
            'feedback' => ['nullable', 'string', 'max:2000'],
            // Mini-leçon personnelle : jamais une raison de refuser le verdict (nettoyée par lesson()).
            'lesson' => ['nullable', 'array'],
        ];
    }

    public function level(): Level
    {
        return Level::from($this->validated('level'));
    }

    public function feedback(): string
    {
        return trim((string) $this->validated('feedback', ''));
    }

    /**
     * Une ou deux vraies erreurs de l'apprenant (ou une tournure plus naturelle), nettoyées : texte seul, raccourci,
     * éléments sans bonne forme ou sans explication ignorés.
     *
     * @return list<array{said: string, correct: string, why: string}>
     */
    public function lesson(): array
    {
        $clean = fn ($value, int $max) => is_string($value) ? Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($value))), $max, '…') : '';
        $items = [];

        foreach ((array) $this->validated('lesson', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $correct = $clean($item['correct'] ?? null, 160);
            $why = $clean($item['why'] ?? null, 240);

            if ($correct !== '' && $why !== '') {
                $items[] = ['said' => $clean($item['said'] ?? null, 160), 'correct' => $correct, 'why' => $why];
            }
        }

        return array_slice($items, 0, 2);
    }
}
