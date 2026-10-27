<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\Profile;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Programme personnel d'un apprenant pour un niveau : les 12 unités du niveau,
 * découpées en leçons et réparties sur ses jours de travail.
 */
#[Fillable([
    'level', 'status', 'start_level', 'target_level', 'goal', 'other_goals', 'profile', 'occupation', 'interests',
    'study_days', 'study_time', 'difficulties', 'content', 'is_active',
])]
class Program extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProgramStatus::Active->value,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'status' => ProgramStatus::class,
            'start_level' => Level::class,
            'target_level' => Level::class,
            'goal' => Goal::class,
            'profile' => Profile::class,
            'other_goals' => 'array',
            'study_days' => 'array',
            'difficulties' => 'array',
            'content' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Autres objectifs (deux au plus, jamais l'objectif principal) ; valeurs inconnues ignorées.
     *
     * @return list<Goal>
     */
    public function otherGoals(): array
    {
        return array_values(array_filter(
            array_map(fn ($value) => Goal::tryFrom((string) $value), (array) $this->other_goals),
            fn (?Goal $goal) => $goal !== null && $goal !== $this->goal,
        ));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProgramLesson, $this>
     */
    public function programLessons(): HasMany
    {
        return $this->hasMany(ProgramLesson::class)->orderBy('position');
    }

    /**
     * La prochaine leçon à faire (la première non terminée).
     *
     * @return HasOne<ProgramLesson, $this>
     */
    public function currentLesson(): HasOne
    {
        return $this->hasOne(ProgramLesson::class)->ofMany(
            ['position' => 'min'],
            fn ($query) => $query->where('status', ProgramLessonStatus::Pending),
        );
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * @return list<Difficulty>
     */
    public function difficultyCases(): array
    {
        return array_values(array_filter(array_map(Difficulty::tryFrom(...), $this->difficulties ?? [])));
    }

    /**
     * @return array<int, Unit>
     */
    public function units(): array
    {
        return Curriculum::units($this->level);
    }

    public function unit(int $number): Unit
    {
        return Curriculum::unit($this->level, $number);
    }

    public function progressPercent(): int
    {
        $total = $this->programLessons()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->programLessons()->where('status', ProgramLessonStatus::Completed)->count();

        return (int) floor($done * 100 / $total);
    }

    /**
     * Libellé des jours de travail : « lundi, mercredi et vendredi ».
     */
    public function studyDaysLabel(): string
    {
        $names = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        $days = array_map(fn ($day) => $names[(int) $day] ?? '', $this->study_days ?? []);

        if (count($days) <= 1) {
            return implode('', $days);
        }

        $last = array_pop($days);

        return implode(', ', $days).' et '.$last;
    }

    /**
     * Répartit les leçons restantes sur les prochains jours de travail, à partir de $from.
     */
    public function reschedule(?Carbon $from = null, int $lessonsPerDay = 1): void
    {
        $days = array_map('intval', $this->study_days ?: [1, 2, 3, 4, 5]);
        $date = ($from ?? today())->copy()->startOfDay();
        $lessonsPerDay = max(1, $lessonsPerDay);
        $placed = 0;

        foreach ($this->programLessons()->where('status', ProgramLessonStatus::Pending)->get() as $lesson) {
            while (! in_array($date->dayOfWeekIso, $days, true)) {
                $date->addDay();
            }

            $lesson->update(['scheduled_on' => $date->toDateString()]);

            if (++$placed % $lessonsPerDay === 0) {
                $date->addDay();
            }
        }
    }
}
