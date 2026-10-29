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

    /**
     * Professeurs que l'apprenant peut choisir : ceux dont la voix ET le visage sont prêts (« ready »).
     *
     * @return array<string, Teacher>
     */
    public static function choosable(): array
    {
        return array_filter(self::all(), fn (Teacher $teacher) => $teacher->isReady());
    }

    /** Le choix du professeur est-il proposé aux apprenants (au moins deux professeurs prêts) ? */
    public static function hasChoice(): bool
    {
        return count(self::choosable()) > 1;
    }

    /**
     * Professeur choisi par l'apprenant s'il peut encore être choisi, sinon le professeur par défaut : retirer un
     * professeur de la liste (ou le marquer pas prêt) ramène ses apprenants vers Teacher Joe.
     */
    public static function forUser(?User $user): Teacher
    {
        $teacher = self::find($user?->getAttributes()['teacher'] ?? null);

        return $teacher !== null && $teacher->isReady() ? $teacher : self::default();
    }
}
