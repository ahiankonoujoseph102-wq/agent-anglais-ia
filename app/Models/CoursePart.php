<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une partie d'une leçon « Mes cours » en PDF, choisie par l'élève avant la séance : des pages (« 3 à 5 ») ou un
 * passage décrit (« l'exercice 2 »). Lue une fois (pages du PDF au-delà de courses.part_max_pages) et gardée :
 * l'élève y revient un autre jour sans renvoyer le fichier.
 */
#[Fillable(['page_from', 'page_to', 'request', 'content', 'text', 'provider'])]
class CoursePart extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_from' => 'integer',
            'page_to' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CourseLesson, $this>
     */
    public function courseLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class);
    }

    /**
     * « pages 3 à 5 », « page 4 », « l'exercice 2 » ou les deux.
     */
    public function label(): string
    {
        $pages = match (true) {
            $this->page_from === null => null,
            $this->page_from === $this->page_to => 'page '.$this->page_from,
            default => 'pages '.$this->page_from.' à '.$this->page_to,
        };

        return implode(' · ', array_filter([$pages, $this->request ? '« '.$this->request.' »' : null])) ?: 'toute la leçon';
    }
}
