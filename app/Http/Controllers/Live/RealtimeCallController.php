<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Support\Live\LiveLog;
use App\Support\Live\LiveProviders;
use App\Support\Live\LiveUnavailable;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Ouverture d'un appel OpenAI (séance en direct) : le navigateur envoie sa description WebRTC à l'adresse à
 * usage unique délivrée par la connexion ; le serveur ouvre l'appel chez OpenAI avec la clé et les consignes,
 * garde l'identifiant de l'appel (pour raccrocher à la limite) et renvoie la réponse WebRTC.
 */
class RealtimeCallController extends Controller
{
    public function store(Request $request, string $ticket, OpenAiRealtime $openai): JsonResponse
    {
        $request->validate(['sdp' => ['required', 'string', 'max:20000']]);

        $data = Cache::pull('live-call:'.$ticket);

        if (! is_array($data) || (int) $data['user_id'] !== (int) $request->user()->id) {
            return response()->json(['message' => 'Cette connexion a expiré. Touchez « Reprendre la séance ».'], 410);
        }

        $conversation = $data['class'] !== null ? $data['class']::query()->find($data['id']) : null;

        // Séance close entre-temps (temps écoulé, page fermée) : aucun appel n'est ouvert.
        if ($data['class'] !== null && ($conversation === null || ! $conversation->closeIfOver())) {
            return response()->json(['message' => 'Cette séance est terminée.'], 409);
        }

        try {
            $call = $openai->createCall((string) $request->input('sdp'), $data['session'], OpenAiRealtime::safetyId($request->user()->id));
        } catch (LiveUnavailable $e) {
            LiveLog::failure(LiveProviders::OPENAI, 'Ouverture de l\'appel', $e->getMessage(), $e->httpStatus, $e->quotaExceeded);

            if ($e->pausesProvider()) {
                LiveProviders::pause(LiveProviders::OPENAI);
            }

            return response()->json(['message' => $e->userMessage(), 'quota' => $e->quotaExceeded], 503);
        }

        if ($conversation !== null) {
            // Nouvel appel de la même séance (reconnexion) : l'ancien est raccroché.
            $previous = $conversation->getAttributes()['live_call_id'] ?? null;
            $conversation->forceFill(['live_call_id' => $call['call_id']])->save();

            if (filled($previous) && $previous !== $call['call_id']) {
                $openai->hangup((string) $previous);
            }
        } else {
            // Essai de l'administrateur (diagnostic) : raccroché au plus tard à la fin des secondes prévues.
            Cache::put('live-trial-call:'.$request->user()->id, ['call_id' => $call['call_id'], 'ends_at' => now()->addSeconds((int) $data['trial_seconds'])->getTimestamp()], now()->addMinutes(10));
        }

        return response()->json(['sdp' => $call['sdp']]);
    }
}
