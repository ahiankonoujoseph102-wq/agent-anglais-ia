<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tableau d'une leçon (phrases clés, vocabulaire, verbes, règle), préparé une fois par le modèle texte et
 * partagé : voir App\Support\Curriculum\LessonBoards.
 */
#[Fillable(['key', 'level', 'unit_number', 'lesson_number', 'situation_index', 'goal', 'content', 'provider'])]
class LessonBoard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_number' => 'integer',
            'lesson_number' => 'integer',
            'situation_index' => 'integer',
            'content' => 'array',
        ];
    }
}
