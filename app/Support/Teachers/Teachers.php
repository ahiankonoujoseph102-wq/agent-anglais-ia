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
     * Noms des professeurs proposés, pour les pages publiques : « Teacher Joe ou Teacher Rose » (un seul professeur
     * proposé : son nom seul, comme avant).
     */
    public static function names(string $last = ' ou '): string
    {
        $names = array_values(array_map(fn (Teacher $teacher) => $teacher->name(), self::choosable()));

        if (count($names) < 2) {
            return self::default()->name();
        }

        $final = array_pop($names);

        return implode(', ', $names).$last.$final;
    }

    /**
     * Nouveaux professeurs à faire connaître à un abonné (annonce « Nouveau : … est arrivée ! » de « Mon espace ») :
     * proposés, autres que le professeur par défaut et que le sien, et avec qui il n'a jamais fait aucune séance.
     * Seulement pour un apprenant qui a déjà tenu une séance du jour : un nouvel abonné choisit sur la page de
     * bienvenue (« Avec qui voulez-vous travailler ? »).
     *
     * @return list<Teacher>
     */
    public static function untried(User $user): array
    {
        if (! self::hasChoice() || $user->isStaff() || ! $user->lessons()->exists()) {
            return [];
        }

        $current = self::forUser($user)->key;

        return array_values(array_filter(self::choosable(), fn (Teacher $teacher) => ! $teacher->isDefault()
            && $teacher->key !== $current
            && ! $user->lessons()->where('teacher', $teacher->key)->exists()
            && ! $user->courseSessions()->where('teacher', $teacher->key)->exists()
            && ! $user->levelTests()->where('teacher', $teacher->key)->exists()));
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
