<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Live\TranscriptExport;
use App\Support\Live\Transcripts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $lines = Transcripts::lines($conversation);
        $events = Transcripts::events($conversation);

        // Répliques et événements mêlés dans l'ordre du temps (à égalité : la réplique d'abord).
        $timeline = $lines->map(fn ($line) => ['at' => $line->spoken_at, 'order' => 0, 'line' => $line])
            ->concat($events->map(fn ($event) => ['at' => $event->happened_at, 'order' => 1, 'event' => $event]))
            ->sortBy([fn ($a, $b) => $a['at'] <=> $b['at'], fn ($a, $b) => $a['order'] <=> $b['order']])
            ->values();

        return view('admin.conversations.show', [
            'user' => $user,
            'type' => $type,
            'label' => Transcripts::label($type),
            'conversation' => $conversation,
            'verdict' => Transcripts::verdict($conversation),
            'lines' => $lines,
            'timeline' => $timeline,
            'delays' => Transcripts::delays($lines),
            'slowMs' => (int) config('platform.transcripts.slow_reply_seconds') * 1000,
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $deleted = Transcripts::forget($user);

        return redirect()->to(route('admin.users.show', $user).'#conversations')
            ->with('status', 'Les conversations de '.$user->name.' sont supprimées ('.$deleted.' '.($deleted > 1 ? 'répliques' : 'réplique').').');
    }

    /**
     * Export anonymisé des conversations récentes (fichier texte fabriqué à la demande, jamais gardé).
     */
    public function export(Request $request): Response
    {
        $days = (int) $request->query('jours', 30);
        $days = in_array($days, TranscriptExport::PERIODS, true) ? $days : 30;

        return response(TranscriptExport::build($days), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="conversations-anonymisees-'.now()->format('Y-m-d').'.txt"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
