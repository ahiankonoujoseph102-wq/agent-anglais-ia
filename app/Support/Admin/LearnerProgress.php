<?php

namespace App\Support\Admin;

use App\Enums\ErrorStatus;
use App\Enums\LessonStatus;
use App\Enums\ProgramLessonStatus;
use App\Enums\Role;
use App\Enums\Skill;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Live\WorkingTogether;
use App\Support\Reminders\LessonReminders;
use App\Support\Teachers\Teachers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Suivi de près de chaque apprenant par l'équipe (page « Suivi des élèves » et section « Progression et travail » de
 * la fiche d'un inscrit) : séance du jour faite ou non, temps utilisé, régularité, programme, compétences, erreurs à
 * revoir, dernières séances (résumé, devoir, notes). LECTURE SEULE : rien n'est écrit, aucun modèle n'est appelé.
 */
final class LearnerProgress
{
    /** Séance du jour tenue (assez longue pour compter, comme pour les rappels). */
    public const DONE = 'done';

    public const IN_PROGRESS = 'in_progress';

    /** Commencée aujourd'hui mais trop courte pour compter. */
    public const SHORT = 'short';

    public const NONE = 'none';

    public const TODAY_LABELS = [
        self::DONE => 'Séance faite',
        self::IN_PROGRESS => 'Séance en cours',
        self::SHORT => 'Séance commencée, trop courte',
        self::NONE => 'Pas encore de séance',
    ];

    /**
     * Apprenants avec un accès en cours (payé, provisoire, offert ou mois d'ambassadeur), jamais l'équipe.
     *
     * @return Collection<int, User>
     */
    public static function followed(): Collection
    {
        return User::query()
            ->where('role', Role::Learner)
            ->whereHas('activeSubscription')
            ->with(['activeSubscription', 'activeProgram.currentLesson'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Ce qu'a fait l'apprenant AUJOURD'HUI (jour de Lomé, comme le temps du jour).
     *
     * @return array{status: string, label: string, used_seconds: int, daily_seconds: int, lesson_seconds: int, course_seconds: int, working_day: bool|null, study_time: string|null, started_at: Carbon|null}
     */
    public static function today(User $user): array
    {
        $lessons = $user->lessons()->whereDate('date', today())->get(['id', 'status', 'used_seconds', 'started_at']);
        $courseSeconds = (int) $user->courseSessions()->whereDate('date', today())->sum('used_seconds');
        $held = WorkingTogether::heldSeconds();

        $status = match (true) {
            $lessons->contains(fn (Lesson $lesson) => $lesson->status === LessonStatus::InProgress) => self::IN_PROGRESS,
            $lessons->contains(fn (Lesson $lesson) => (int) $lesson->used_seconds >= $held) => self::DONE,
            $lessons->isNotEmpty() => self::SHORT,
            default => self::NONE,
        };

        $program = $user->activeProgram;
        $days = array_map('intval', $program?->study_days ?? []);
        $time = filled($program?->study_time) ? substr((string) $program->study_time, 0, 5) : null;

        return [
            'status' => $status,
            'label' => self::TODAY_LABELS[$status],
            'used_seconds' => $user->secondsUsedToday(false),
            'daily_seconds' => (int) $user->activeSubscription?->dailySeconds(),
            'lesson_seconds' => (int) $lessons->sum('used_seconds'),
            'course_seconds' => $courseSeconds,
            // Jour de travail choisi au questionnaire (null : pas encore de programme).
            'working_day' => $program === null ? null : in_array(today()->dayOfWeekIso, $days, true),
            'study_time' => $time === null ? null : LessonReminders::timeLabel($time),
            'started_at' => $lessons->min('started_at'),
        ];
    }

    /**
     * Ligne du tableau « Suivi des élèves ».
     *
     * @return array<string, mixed>
     */
    public static function row(User $user): array
    {
        $program = $user->activeProgram;
        $current = $program?->currentLesson;
        $current?->setRelation('program', $program);

        return [
            'user' => $user,
            'today' => self::today($user),
            'teacher' => Teachers::forUser($user)->name(),
            'last' => self::lastSession($user),
            'streak' => $user->streakDays(),
            'missed' => LessonReminders::missedDays($user),
            'progress' => $program?->progressPercent(),
            'lessons_done' => $program?->programLessons()->where('status', ProgramLessonStatus::Completed)->count(),
            'lessons_total' => $program?->programLessons()->count(),
            'current' => $current === null ? null : 'Unité '.$current->unit_number.' : '.$current->unit()->title.', leçon '.$current->lesson_number,
            'open_errors' => $user->openErrors()->count(),
        ];
    }

    /**
     * Tout le tableau, les élèves sans séance aujourd'hui (un jour de travail) d'abord.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(?string $filter = null): Collection
    {
        $order = [self::NONE => 0, self::SHORT => 1, self::IN_PROGRESS => 2, self::DONE => 3];

        return self::followed()
            ->map(fn (User $user) => self::row($user))
            ->filter(fn (array $row) => match ($filter) {
                'pas-encore' => in_array($row['today']['status'], [self::NONE, self::SHORT], true),
                'faite' => in_array($row['today']['status'], [self::DONE, self::IN_PROGRESS], true),
                default => true,
            })
            ->sortBy(fn (array $row) => [
                $order[$row['today']['status']],
                $row['today']['working_day'] === false ? 1 : 0,
                $row['user']->name,
            ])
            ->values();
    }

    /**
     * Chiffres du haut de la page : combien ont fait leur séance aujourd'hui.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total: int, done: int, in_progress: int, not_yet: int, not_yet_working: int}
     */
    public static function counts(Collection $rows): array
    {
        $notYet = $rows->filter(fn (array $row) => in_array($row['today']['status'], [self::NONE, self::SHORT], true));

        return [
            'total' => $rows->count(),
            'done' => $rows->where('today.status', self::DONE)->count(),
            'in_progress' => $rows->where('today.status', self::IN_PROGRESS)->count(),
            'not_yet' => $notYet->count(),
            'not_yet_working' => $notYet->filter(fn (array $row) => $row['today']['working_day'] === true)->count(),
        ];
    }

    /** Dernière séance (séance du jour ou « Mes cours ») d'au moins une minute. */
    public static function lastSession(User $user): ?Carbon
    {
        $dates = array_filter([
            $user->lessons()->where('used_seconds', '>=', 60)->max('started_at'),
            $user->courseSessions()->where('used_seconds', '>=', 60)->max('started_at'),
        ]);

        return $dates === [] ? null : Carbon::parse(max($dates));
    }

    /**
     * Tout le travail de l'apprenant, pour la section « Progression et travail » de sa fiche.
     *
     * @return array<string, mixed>
     */
    public static function details(User $user, int $lessons = 15): array
    {
        $program = $user->activeProgram;
        $units = [];

        if ($program !== null) {
            $program->loadMissing('programLessons');
            $current = $program->currentLesson;

            foreach ($program->programLessons->groupBy('unit_number') as $number => $items) {
                $units[] = [
                    'number' => (int) $number,
                    'title' => $program->unit((int) $number)->title,
                    'done' => $items->where('status', ProgramLessonStatus::Completed)->count(),
                    'total' => $items->count(),
                    'current' => $current !== null && (int) $current->unit_number === (int) $number,
                ];
            }
        }

        $skills = [];

        foreach ($user->skillAverages() as $key => $average) {
            $skills[Skill::from($key)->label()] = $average;
        }

        return [
            'row' => self::row($user),
            'program' => $program,
            'units' => $units,
            'upcoming' => $program?->programLessons()->where('status', ProgramLessonStatus::Pending)->orderBy('position')->limit(5)->get()
                ->each(fn ($lesson) => $lesson->setRelation('program', $program)) ?? collect(),
            'skills' => $skills,
            'errors' => $user->openErrors()->get(),
            'resolved_errors' => $user->learnerErrors()->where('status', '!=', ErrorStatus::Open)->count(),
            'lessons' => $user->lessons()->with('programLesson')->latest('started_at')->limit($lessons)->get(),
            'lessons_done' => $user->lessons()->where('status', LessonStatus::Completed)->count(),
            'course_sessions' => $user->courseSessions()->with(['lesson:id,title', 'review'])->latest('started_at')->limit(10)->get(),
            'level_tests' => $user->levelTests()->latest('id')->get(),
            'attestations' => $user->attestations()->orderBy('level')->get(),
        ];
    }
}
