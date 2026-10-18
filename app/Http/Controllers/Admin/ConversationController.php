<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Live\Transcripts;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Texte des conversations d'un apprenant (jamais l'audio) : lecture d'une séance par l'administrateur, et
 * suppression de toutes ses conversations (demande de suppression). Liste des séances : fiche de l'inscrit.
 */
class ConversationController extends Controller
{
    public function show(User $user, string $type, int $id): View
    {
        $conversation = Transcripts::find($type, $id, $user);
        abort_if($conversation === null, 404);

        return view('admin.conversations.show', [
            'user' => $user,
            'type' => $type,
            'label' => Transcripts::label($type),
            'conversation' => $conversation,
            'verdict' => Transcripts::verdict($conversation),
            'lines' => Transcripts::lines($conversation),
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $deleted = Transcripts::forget($user);

        return redirect()->to(route('admin.users.show', $user).'#conversations')
            ->with('status', 'Les conversations de '.$user->name.' sont supprimées ('.$deleted.' '.($deleted > 1 ? 'répliques' : 'réplique').').');
    }
}
