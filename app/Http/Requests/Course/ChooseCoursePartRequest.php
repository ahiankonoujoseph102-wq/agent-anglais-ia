<?php

namespace App\Http\Requests\Course;

use App\Models\CourseLesson;
use App\Models\CoursePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * « Mes cours » : partie d'un PDF à travailler à la prochaine séance. Des pages (« de la page 3 à la page 5 »,
 * courses.part_max_pages pages au plus), un passage décrit (« l'exercice 2 »), ou les deux ; ou une partie déjà
 * lue (part), pour la reprendre sans relire le fichier.
 */
class ChooseCoursePartRequest extends FormRequest
{
    /**
     * Seulement une leçon de l'apprenant (sinon 404, comme partout dans « Mes cours »).
     */
    public function authorize(): bool
    {
        abort_unless($this->route('course')?->user_id === $this->user()?->id, 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'part' => ['nullable', 'integer'],
            'from' => ['nullable', 'integer', 'min:1'],
            'to' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:'.(int) config('platform.courses.part_description_max')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->existingPart() !== null) {
                return;
            }

            /** @var CourseLesson $course */
            $course = $this->route('course');
            $max = (int) config('platform.courses.part_max_pages');
            $from = $this->integer('from') ?: null;
            $to = $this->integer('to') ?: null;

            if ($from === null && $to === null && $this->description() === null) {
                $validator->errors()->add('from', "Indiquez les pages à travailler (par exemple de la page 3 à la page 5), ou décrivez le passage (par exemple « l'exercice 2 »).");

                return;
            }

            if ($from === null && $to !== null) {
                $validator->errors()->add('from', 'Indiquez aussi la première page.');
            } elseif ($from !== null && ($to ?? $from) < $from) {
                $validator->errors()->add('to', 'La dernière page doit venir après la première.');
            } elseif ($from !== null && ($to ?? $from) > $course->page_count) {
                $validator->errors()->add('to', "Ce PDF a {$course->page_count} pages : choisissez des pages entre 1 et {$course->page_count}.");
            } elseif ($from !== null && ($to ?? $from) - $from + 1 > $max) {
                $validator->errors()->add('to', "Choisissez {$max} pages au plus à la fois (par exemple de la page 3 à la page 5). Vous travaillerez la suite un autre jour.");
            }
        }];
    }

    public function pages(): array
    {
        $from = $this->integer('from') ?: null;

        return [$from, $from === null ? null : ($this->integer('to') ?: $from)];
    }

    public function description(): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $this->input('description')) ?? '');

        return $text === '' ? null : $text;
    }

    /**
     * Partie déjà lue de cette leçon, choisie de nouveau (« Reprendre »).
     */
    public function existingPart(): ?CoursePart
    {
        /** @var CourseLesson $course */
        $course = $this->route('course');

        return $this->filled('part') ? $course->parts()->whereKey($this->integer('part'))->first() : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.integer' => 'La première page doit être un nombre.',
            'from.min' => 'Les pages commencent à 1.',
            'to.integer' => 'La dernière page doit être un nombre.',
            'to.min' => 'Les pages commencent à 1.',
            'description.max' => 'Décrivez le passage en '.(int) config('platform.courses.part_description_max').' caractères au plus.',
        ];
    }
}
