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
 * (marqueur :context de config/teacher.php) : profil, objectif (et sa façon d'adapter la pratique), unité et leçon du jour,
 * séance précédente, erreurs à revoir.
 */
class LessonContext
{
    /**
     * @param  Collection<int, LearnerError>  $errors
     * @param  array<string, mixed>|null  $board  tableau de la leçon (LessonBoards::for), sinon celui de secours
     * @param  array{lesson: int, practice: int, conversation: int, summary: int, merged: bool, minutes: int}|null  $plan  temps de chaque étape (LessonPlan)
     * @param  list<array{en: string, fr: ?string, days: int}>  $review  phrases des leçons passées à redemander (SpacedReview)
     */
    public static function describe(User $user, Program $program, ProgramLesson $current, ?Lesson $previous, Collection $errors, ?array $board = null, ?array $plan = null, array $review = []): string
    {
        $board ??= LessonBoards::for($current, $program);
        $unit = $current->unit();
        $situation = $current->situation();
        $lines = [];

        $lines[] = 'LEARNER PROFILE';
        $lines[] = '- First name: '.$user->name;
        $lines[] = '- Level: '.$program->level->value.' ('.$program->level->label().')';
        $lines[] = '- Main goal: '.$program->goal->value.' ('.$program->goal->label().')';

        if ($program->profile) {
            $lines[] = '- Profile: '.$program->profile->value.' ('.$program->profile->label().')';
        }

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

        // Objectif particulier (config/teacher.php → goal_focus) : comment adapter la situation du jour.
        if (filled($focus = config('teacher.goal_focus.'.$program->goal->value))) {
            $lines[] = '- How to adapt today\'s practice to the learner\'s goal: '.trim((string) $focus);
        }

        if ($current->attempts > 0) {
            $lines[] = '- This lesson was already started '.$current->attempts.' time(s) and must be finished: consolidate before moving on.';
        }

        if ($plan !== null) {
            $lines[] = '';
            $lines[] = "TODAY'S PLAN (".$plan['minutes'].' minutes)';
            $lines[] = $plan['merged']
                ? '- STEP 1 and STEP 2 together (lesson and guided practice, short session): about '.$plan['lesson'].' minute(s)'
                : '- STEP 1, lesson: about '.$plan['lesson'].' minute(s)'."\n".'- STEP 2, guided practice: about '.$plan['practice'].' minute(s)';
            $lines[] = '- STEP 3, conversation: about '.$plan['conversation'].' minute(s)';
            $lines[] = '- STEP 4, summary: the last '.$plan['summary'].' minute(s)';
        }

        $lines[] = '';
        $lines[] = "TODAY'S BOARD (shown on the learner's screen; teach it in this order)";
        $lines[] = '- Title: '.$board['title'];
        $lines[] = '- Key phrases:';

        foreach ($board['phrases'] as $phrase) {
            $lines[] = '  * "'.$phrase['en'].'"'.($phrase['fr'] ? ' = '.$phrase['fr'] : '').(! empty($phrase['use']) ? ' (use: '.$phrase['use'].')' : '');
        }

        $lines[] = '- Words:';

        foreach ($board['vocabulary'] as $word) {
            $lines[] = '  * '.$word['en'].($word['fr'] ? ' = '.$word['fr'] : '');
        }

        if ($board['verbs'] !== []) {
            $lines[] = '- Verbs:';

            foreach ($board['verbs'] as $verb) {
                $lines[] = '  * '.$verb['forms'].($verb['fr'] ? ' = '.$verb['fr'] : '');
            }
        }

        if ($board['rule']['en'] || $board['rule']['fr']) {
            $lines[] = '- Rule: '.implode(' / ', array_filter([$board['rule']['en'], $board['rule']['fr'] ? '(in French) '.$board['rule']['fr'] : null]));
        }

        $lines[] = '';
        $lines[] = 'PREVIOUS LESSON';

        if ($previous?->summary) {
            $lines[] = '- Summary: '.$previous->summary;
            $lines[] = '- Homework given: '.($previous->homework ?: 'none');
            $lines[] = '- Use them for the WARM-UP: the learner recalls the sentence to remember, then tells you about the homework.';
        } else {
            $lines[] = '- None: this is the first lesson of the programme (no warm-up today).';
        }

        $lines[] = '';
        $lines[] = 'PHRASES TO REVIEW (spaced review of past lessons, in STEP 2: the learner recalls them)';

        if ($review === []) {
            $lines[] = '- None today.';
        }

        foreach ($review as $phrase) {
            $lines[] = sprintf('- "%s"%s (learned about %d day(s) ago)', $phrase['en'], $phrase['fr'] ? ' = '.$phrase['fr'] : '', $phrase['days']);
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
