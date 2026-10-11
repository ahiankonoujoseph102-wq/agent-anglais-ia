<?php

namespace App\Models;

use App\Enums\ProgramLessonStatus;
use App\Support\Curriculum\Situation;
use App\Support\Curriculum\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une leçon du programme : une unité, un numéro de leçon (1 à 3), une situation, une date.
 */
#[Fillable(['position', 'unit_number', 'lesson_number', 'situation_index', 'highlighted', 'scheduled_on', 'status', 'attempts', 'completed_at'])]
class ProgramLesson extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProgramLessonStatus::Pending->value,
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'unit_number' => 'integer',
            'lesson_number' => 'integer',
            'situation_index' => 'integer',
            'highlighted' => 'boolean',
            'scheduled_on' => 'date',
            'status' => ProgramLessonStatus::class,
            'attempts' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ProgramLessonStatus::Completed;
    }

    public function unit(): Unit
    {
        return $this->program->unit($this->unit_number);
    }

    public function situation(): Situation
    {
        return $this->unit()->situations[$this->situation_index];
    }

    /**
     * Ce que travaille cette leçon dans l'unité (voir Unit::lessonFocus()).
     */
    public function focusLabel(): string
    {
        return Unit::lessonFocusLabel($this->lesson_number);
    }
}
