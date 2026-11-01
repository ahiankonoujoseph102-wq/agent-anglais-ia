<?php

namespace App\Support\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * « Voir son espace » : l'administrateur PRINCIPAL regarde les pages d'un apprenant telles que celui-ci les voit,
 * en LECTURE SEULE (middleware App\Http\Middleware\ViewAsLearner).
 *
 * - Rien n'est jamais écrit au nom de l'apprenant : tout envoi (POST, PUT, PATCH, DELETE) est refusé, et les pages
 *   qui écriraient ou appelleraient un fournisseur en s'ouvrant (bienvenue, messages vocaux) sont refusées aussi.
 * - Ni mot de passe, ni connexion de l'apprenant : la session reste celle de l'administrateur ; seules les pages de
 *   l'apprenant (hors /admin) sont montrées avec son compte, le temps d'une requête.
 * - Limité dans le temps (MINUTES), arrêté par « Revenir à l'administration », et noté dans le journal.
 */
final class ViewAs
{
    public const SESSION_KEY = 'view_as';

    public const MINUTES = 60;

    /** Pages refusées même en lecture : elles écrivent ou appellent un fournisseur en s'ouvrant. */
    public const BLOCKED_ROUTES = [
        'welcome.show',
        'welcome.audio',
        'assessment.voice',
        'assessment.voice.status',
    ];

    public const READ_ONLY = 'Lecture seule : rien n\'est modifié dans l\'espace de l\'apprenant.';

    /** Apprenant regardé pendant CETTE requête (posé par le middleware). */
    private static ?User $current = null;

    /** Seul l'administrateur principal, et seulement le compte d'un apprenant. */
    public static function allowed(?User $admin, User $learner): bool
    {
        return $admin !== null && $admin->isAdmin() && $learner->role === Role::Learner;
    }

    public static function start(Request $request, User $learner): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $learner->id,
            'admin_id' => $request->user()->id,
            'until' => now()->addMinutes(self::MINUTES)->getTimestamp(),
        ]);

        Log::info('Administration : consultation de l\'espace d\'un apprenant en lecture seule.', [
            'admin_id' => $request->user()->id,
            'learner_id' => $learner->id,
        ]);
    }

    public static function stop(Request $request): ?int
    {
        $data = $request->session()->pull(self::SESSION_KEY);

        return is_array($data) ? (int) ($data['user_id'] ?? 0) ?: null : null;
    }

    /**
     * L'apprenant à montrer pour cette requête, ou null (consultation terminée, expirée ou pas pour cet administrateur).
     */
    public static function learnerFor(Request $request, ?User $admin): ?User
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data) || $admin === null || ! $admin->isAdmin() || (int) ($data['admin_id'] ?? 0) !== $admin->id) {
            return null;
        }

        if ((int) ($data['until'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $learner = User::query()->find((int) ($data['user_id'] ?? 0));

        return $learner !== null && self::allowed($admin, $learner) ? $learner : null;
    }

    public static function begin(User $learner): void
    {
        self::$current = $learner;
    }

    public static function end(): void
    {
        self::$current = null;
    }

    public static function active(): bool
    {
        return self::$current !== null;
    }

    public static function learner(): ?User
    {
        return self::$current;
    }
}
