<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Rôle d'assistant (App\Support\Admin\AdminAccess) : nommé ou retiré par l'administrateur principal SEUL, depuis
 * la fiche d'un inscrit. Chaque assistant a son propre compte Talkami et son propre mot de passe : jamais de mot
 * de passe partagé. Un administrateur ne devient jamais assistant, et personne ne devient administrateur ici
 * (seulement par la commande app:create-admin).
 */
class AssistantController extends Controller
{
    public function appoint(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Un administrateur ne peut pas devenir assistant.');

        $user->forceFill(['role' => Role::Assistant])->save();

        return redirect()->to(route('admin.users.show', $user).'#role')
            ->with('status', "{$user->name} est maintenant assistant : il le verra à sa prochaine page.");
    }

    public function remove(User $user): RedirectResponse
    {
        abort_unless($user->isAssistant(), 404);

        $user->forceFill(['role' => Role::Learner])->save();

        return redirect()->to(route('admin.users.show', $user).'#role')
            ->with('status', "{$user->name} n'est plus assistant : il n'a plus accès à l'administration.");
    }
}
