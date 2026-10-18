<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Http\Requests\Live\TranscriptRequest;
use App\Support\Live\Transcripts;
use Illuminate\Http\JsonResponse;

/**
 * Réception du texte d'une conversation (jamais l'audio), envoyé par le navigateur par petits lots pendant la
 * séance, puis à la fin. Seulement pour une séance de l'utilisateur connecté, commencée récemment.
 */
class TranscriptController extends Controller
{
    public function store(TranscriptRequest $request, string $type, int $id): JsonResponse
    {
        $conversation = Transcripts::find($type, $id, $request->user());

        if ($conversation === null) {
            return response()->json(['message' => 'Séance introuvable.'], 404);
        }

        if (! Transcripts::accepts($conversation)) {
            return response()->json(['message' => 'Cette séance est trop ancienne.'], 409);
        }

        return response()->json([
            'kept' => Transcripts::record($conversation, $request->lines()),
            'events' => Transcripts::recordEvents($conversation, $request->events()),
        ]);
    }
}
