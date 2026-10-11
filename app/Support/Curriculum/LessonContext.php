<?php

namespace App\Support\Curriculum;

use App\Enums\Difficulty;
use App\Models\LearnerError;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\ProgramLesson;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Le contexte de la séance du jour, inséré dans les consignes du professeur
 * (marqueur :context de config/teacher.php) : profil, unité et leçon du jour,
 * séance précédente, erreurs à revoir.
 */
class LessonContext
{
    /**
     * @param  Collection<int, LearnerError>  $errors
     */
    public static function describe(User $user, Program $program, ProgramLesson $current, ?Lesson $previous, Collection $errors): string
    {
        $unit = $current->unit();
        $situation = $current->situation();
        $lines = [];

        $lines[] = 'LEARNER PROFILE';
        $lines[] = '- First name: '.$user->name;
        $lines[] = '- Level: '.$program->level->value.' ('.$program->level->label().')';
        $lines[] = '- Main goal: '.$program->goal->value.' ('.$program->goal->label().')';
        $lines[] = '- Occupation / situation: '.$program->occupation;

        if ($program->interests) {
            $lines[] = '- Interests: '.$program->interests;
        }

        $lines[] = '- Finds most difficult: '.implode(', ', array_map(fn (Difficulty $d) => $d->value, $program->difficultyCases()));

        $lines[] = '';
        $lines[] = "TODAY'S LESSON";
        $lines[] = sprintf('- Unit %d of %d: "%s" — lesson %d of %d (focus: %s)',
            $unit->number, Curriculum::UNITS_PER_LEVEL, $unit->title, $current->lesson_number, Unit::LESSONS, self::focus($current->lesson_number));
        $lines[] = '- Objectives (the learner will be able to): '.implode(' / ', $unit->objectives);
        $lines[] = '- Key vocabulary (English = French): '.implode('; ', array_map(
            fn ($en, $fr) => $en.' = '.$fr, array_keys($unit->vocabulary), $unit->vocabulary,
        ));
        $lines[] = '- Grammar: '.implode(' / ', $unit->grammar);
        $lines[] = '- Speaking functions: '.implode(' / ', $unit->functions);
        $lines[] = '- Pronunciation: '.implode(' / ', $unit->pronunciation);

        if ($unit->spoken !== []) {
            $lines[] = '- Natural spoken English: '.implode(' / ', $unit->spoken);
        }

        if ($unit->pitfalls !== []) {
            $lines[] = '- Typical difficulties of French speakers: '.implode(' / ', $unit->pitfalls);
        }

        $lines[] = sprintf('- Conversation situation for today: "%s" — %s%s',
            $situation->title, $situation->description, $current->highlighted ? ' (chosen because it matches the learner\'s goal or job)' : '');

        if ($current->attempts > 0) {
            $lines[] = '- This lesson was already started '.$current->attempts.' time(s) and must be finished: consolidate before moving on.';
        }

        $lines[] = '';
        $lines[] = 'PREVIOUS LESSON';

        if ($previous?->summary) {
            $lines[] = '- Summary: '.$previous->summary;
            $lines[] = '- Homework given: '.($previous->homework ?: 'none');
        } else {
            $lines[] = '- None: this is the first lesson of the programme.';
        }

        $lines[] = '';
        $lines[] = 'ERRORS TO REVIEW (use the id in end_lesson.reviewed_errors)';

        if ($errors->isEmpty()) {
            $lines[] = '- None for now.';
        }

        foreach ($errors as $error) {
            $lines[] = sprintf('- [%d] wrong: "%s" → correct: "%s"%s', $error->id, $error->wrong, $error->correct,
                $error->explanation ? ' ('.$error->explanation.')' : '');
        }

        return implode("\n", $lines);
    }

    private static function focus(int $lessonNumber): string
    {
        return match ($lessonNumber) {
            1 => 'new vocabulary and speaking functions',
            2 => 'grammar used in the situation',
            default => 'pronunciation and review of the whole unit',
        };
    }
}
