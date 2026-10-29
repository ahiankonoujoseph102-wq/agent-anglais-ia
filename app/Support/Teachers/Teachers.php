<?php

namespace App\Support\Teachers;

use App\Models\User;

/**
 * Professeurs de la plateforme (config/teachers.php) et professeur d'un apprenant. Le choix de l'apprenant est gardé
 * sur son compte (users.teacher) ; chaque séance garde le professeur avec qui elle a été faite (colonne teacher).
 * Une clé inconnue (professeur retiré, valeur abîmée) donne toujours le professeur par défaut.
 */
class Teachers
{
    /**
     * @return array<string, Teacher>
     */
    public static function all(): array
    {
        $teachers = [];

        foreach ((array) config('teachers.list', []) as $key => $config) {
            $teachers[(string) $key] = new Teacher((string) $key, (array) $config);
        }

        return $teachers;
    }

    public static function find(?string $key): ?Teacher
    {
        return filled($key) ? (self::all()[$key] ?? null) : null;
    }

    public static function default(): Teacher
    {
        return self::find((string) config('teachers.default')) ?? array_values(self::all())[0];
    }

    /** Professeur choisi par l'apprenant, sinon le professeur par défaut. */
    public static function forUser(?User $user): Teacher
    {
        return self::find($user?->getAttributes()['teacher'] ?? null) ?? self::default();
    }

    /** Professeur d'un apprenant connu par son identifiant (création d'une séance) ; aucune requête avec un seul professeur. */
    public static function forUserId(?int $userId): Teacher
    {
        if ($userId === null || count(self::all()) < 2) {
            return self::default();
        }

        return self::forUser(User::query()->select(['id', 'teacher'])->find($userId));
    }
}
