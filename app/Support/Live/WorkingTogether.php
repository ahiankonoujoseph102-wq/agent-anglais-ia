<?php

namespace App\Support\Live;

use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\User;

/**
 * « Comment travailler avec moi » (config/teacher.php → working_together) : au début des premières séances,
 * Teacher Joe explique en quelques mots que l'élève peut poser ses questions en français, demander de répéter,
 * de ralentir ou de réexpliquer, et qu'il a le droit de se tromper ; ensuite, une seule phrase de rappel.
 * Les « phrases utiles » de l'écran (teacher.useful_phrases) disent la même chose.
 *
 * Une séance « compte » quand elle a duré au moins working_together.held_seconds : une séance coupée au bout
 * de quelques secondes ne fait pas perdre l'accueil. La séance en cours n'est jamais comptée (reprise, jeton
 * renouvelé : même numéro, donc mêmes consignes).
 */
class WorkingTogether
{
    /**
     * Numéro de la séance du jour parmi celles déjà faites par l'apprenant (1 pour la première).
     */
    public static function lessonNumber(User $user, ?Lesson $current = null): int
    {
        return 1 + $user->lessons()
            ->where('used_seconds', '>=', self::heldSeconds())
            ->when($current?->id, fn ($query, $id) => $query->whereKeyNot($id))
            ->count();
    }

    /**
     * Numéro de la séance « Mes cours » parmi celles déjà faites par l'apprenant (1 pour la première).
     */
    public static function courseNumber(User $user, ?CourseSession $current = null): int
    {
        return 1 + $user->courseSessions()
            ->where('used_seconds', '>=', self::heldSeconds())
            ->when($current?->id, fn ($query, $id) => $query->whereKeyNot($id))
            ->count();
    }

    /**
     * Une des premières séances : accueil complet et phrases utiles dépliées à l'écran.
     */
    public static function isFirst(int $number): bool
    {
        return $number <= (int) config('teacher.working_together.first_sessions');
    }

    /**
     * Consignes de la séance du jour (marqueur :working_together de teacher.lesson).
     */
    public static function lesson(int $number): string
    {
        return self::block(self::isFirst($number) ? 'lesson_first' : 'lesson_later', $number);
    }

    /**
     * Consignes d'une séance « Mes cours » (marqueur :working_together de teacher.course).
     */
    public static function course(int $number): string
    {
        return self::block(self::isFirst($number) ? 'course_first' : 'course_later', $number);
    }

    /**
     * Phrases utiles montrées à l'écran.
     *
     * @return list<array{en: string, fr: string}>
     */
    public static function phrases(): array
    {
        return array_values(array_map(
            fn (array $phrase) => ['en' => (string) $phrase['en'], 'fr' => (string) $phrase['fr']],
            (array) config('teacher.useful_phrases'),
        ));
    }

    private static function block(string $key, int $number): string
    {
        return trim(str_replace(':number', (string) $number, (string) config("teacher.working_together.{$key}")))
            ."\n".trim((string) config('teacher.working_together.when_stuck'));
    }

    private static function heldSeconds(): int
    {
        return (int) config('teacher.working_together.held_seconds');
    }
}
