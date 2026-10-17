<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une page d'une leçon « Mes cours » : un fichier du disque privé (photo ou PDF).
 */
#[Fillable(['position', 'path', 'mime', 'size'])]
class CoursePage extends Model
{
    /**
     * @return BelongsTo<CourseLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }
}
