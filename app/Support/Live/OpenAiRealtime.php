<?php

namespace App\Support\Live;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Séances en direct chez OpenAI (API Realtime, WebRTC), secours de Gemini Live.
 *
 * Le navigateur prépare sa connexion WebRTC (micro et canal « oai-events ») et envoie sa description (SDP)
 * à NOTRE serveur, qui ouvre l'appel chez OpenAI avec la clé et les consignes (interface « unifiée ») : ni la
 * clé, ni un jeton, ni les consignes ne passent par le navigateur. Le serveur garde l'identifiant de l'appel et
 * raccroche lui-même à la limite de temps (le jeton de Gemini expire ; un appel OpenAI se raccroche).
 *
 * Documentation : https://developers.openai.com/api/docs/guides/realtime-webrtc,
 * /api/docs/guides/realtime-server-controls et /api/reference/resources/realtime/subresources/calls/methods/hangup.
 */
class OpenAiRealtime
{
    /**
     * Configuration OpenAI tirée de la configuration Gemini du professeur : EXACTEMENT les mêmes consignes
     * (personnage, niveau, déroulé, fiche du site, situation de l'apprenant) et les mêmes fonctions.
     *
     * @param  array<string, mixed>  $setup  configuration « BidiGenerateContentSetup » (TeacherSetup)
     * @return array<string, mixed>
     */
    public static function session(array $setup): array
    {
        $vad = (array) config('services.openai.realtime_vad');
        $tools = [];

        foreach ($setup['tools'][0]['functionDeclarations'] ?? [] as $function) {
            $tools[] = [
                'type' => 'function',
                'name' => $function['name'],
                'description' => $function['description'],
                'parameters' => self::schema($function['parameters']),
            ];
        }

        $session = [
            'type' => 'realtime',
            'model' => (string) config('services.openai.realtime_model'),
            'instructions' => (string) ($setup['systemInstruction']['parts'][0]['text'] ?? ''),
            'output_modalities' => ['audio'],
            'audio' => [
                'input' => [
                    'noise_reduction' => ['type' => 'near_field'],
                    // Sous-titres « Vous » : transcription de ce que dit l'apprenant (anglais ou français).
                    'transcription' => ['model' => (string) config('services.openai.realtime_transcription_model')],
                    // Détection de parole peu sensible : l'apprenant qui hésite n'est pas coupé.
                    'turn_detection' => [
                        'type' => 'server_vad',
                        'threshold' => (float) $vad['threshold'],
                        'prefix_padding_ms' => (int) $vad['prefix_padding_ms'],
                        'silence_duration_ms' => (int) $vad['silence_duration_ms'],
                        'create_response' => true,
                        'interrupt_response' => true,
                    ],
                ],
                'output' => ['voice' => (string) config('services.openai.realtime_voice')],
            ],
        ];

        if ($tools !== []) {
            $session['tools'] = $tools;
            $session['tool_choice'] = 'auto';
        }

        if (filled($effort = config('services.openai.realtime_reasoning'))) {
            $session['reasoning'] = ['effort' => (string) $effort];
        }

        return $session;
    }

    /**
     * Ouvre l'appel chez OpenAI avec la description WebRTC du navigateur.
     *
     * @param  array<string, mixed>  $session
     * @return array{sdp: string, call_id: string}
     *
     * @throws LiveUnavailable
     */
    public function createCall(string $sdp, array $session, ?string $safetyId = null): array
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new LiveUnavailable('OPENAI_API_KEY manquante dans les variables d\'environnement.', provider: LiveProviders::OPENAI);
        }

        try {
            $response = Http::withToken((string) $key)
                ->withHeaders(array_filter(['OpenAI-Safety-Identifier' => $safetyId]))
                ->timeout(20)
                ->asMultipart()
                ->post($this->url('/realtime/calls'), [
                    ['name' => 'sdp', 'contents' => $sdp],
                    ['name' => 'session', 'contents' => json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ]);
        } catch (ConnectionException $e) {
            throw self::connectionFailure($e);
        }

        if ($response->failed()) {
            throw self::failure($response, 'Ouverture de l\'appel refusée');
        }

        $answer = $response->body();
        $callId = basename((string) parse_url((string) $response->header('Location'), PHP_URL_PATH));

        if (! str_starts_with(ltrim($answer), 'v=') || $callId === '' || $callId === '.') {
            throw new LiveUnavailable('OpenAI a répondu sans description de l\'appel.', httpStatus: $response->status(), provider: LiveProviders::OPENAI);
        }

        return ['sdp' => $answer, 'call_id' => $callId];
    }

    /**
     * Raccroche un appel (fin de séance, limite de temps, nouvel appel de la même séance). Jamais en échec :
     * un appel déjà terminé est simplement ignoré.
     */
    public function hangup(string $callId): void
    {
        if (blank(config('services.openai.key')) || $callId === '') {
            return;
        }

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(5)
                ->post($this->url('/realtime/calls/'.rawurlencode($callId).'/hangup'));

            if ($response->failed() && $response->status() !== 404) {
                Log::warning('OpenAI : raccrochage refusé.', ['status' => $response->status(), 'call' => $callId]);
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAI : raccrochage impossible.', ['call' => $callId, 'message' => Str::limit($e->getMessage(), 200)]);
        }
    }

    /**
     * Essai du diagnostic : crée un jeton éphémère de 10 secondes (la durée la plus courte permise) avec le
     * modèle et la voix réglés, sans ouvrir d'appel. Le jeton n'est ni gardé ni affiché.
     *
     * @return array{expires_in: int}
     *
     * @throws LiveUnavailable
     */
    public function testToken(): array
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new LiveUnavailable('Clé absente.', provider: LiveProviders::OPENAI);
        }

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(15)
                ->post($this->url('/realtime/client_secrets'), [
                    'expires_after' => ['anchor' => 'created_at', 'seconds' => 10],
                    'session' => [
                        'type' => 'realtime',
                        'model' => (string) config('services.openai.realtime_model'),
                        'audio' => ['output' => ['voice' => (string) config('services.openai.realtime_voice')]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw self::connectionFailure($e);
        }

        if ($response->failed() || ! is_string($response->json('value'))) {
            throw self::failure($response, 'Création du jeton refusée');
        }

        return ['expires_in' => max(0, (int) $response->json('expires_at') - now()->getTimestamp())];
    }

    /**
     * Identifiant stable et anonyme de l'apprenant, conseillé par OpenAI (jamais son nom ni son numéro).
     */
    public static function safetyId(int $userId): string
    {
        return substr(hash_hmac('sha256', 'learner:'.$userId, (string) config('app.key')), 0, 32);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.openai.base_url'), '/').$path;
    }

    /**
     * Types du schéma en minuscules (Gemini : « OBJECT », OpenAI : « object »).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function schema(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $schema[$key] = strtolower($value);
            } elseif (is_array($value) && $key !== 'required' && $key !== 'enum') {
                $schema[$key] = self::schema($value);
            }
        }

        return $schema;
    }

    private static function connectionFailure(ConnectionException $e): LiveUnavailable
    {
        $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

        return new LiveUnavailable($timedOut ? 'Pas de réponse d\'OpenAI à temps.' : 'Connexion à l\'API OpenAI impossible.', provider: LiveProviders::OPENAI);
    }

    /**
     * Cause d'un refus : crédit épuisé ou limite (429), clé refusée, sinon le code et le message d'OpenAI.
     */
    private static function failure(Response $response, string $what): LiveUnavailable
    {
        $message = Str::limit(trim((string) $response->json('error.message', '')), 200);

        return new LiveUnavailable(
            $what.' (HTTP '.$response->status().')'.($message !== '' ? ' : '.$message : '').'.',
            quotaExceeded: $response->status() === 429,
            httpStatus: $response->status(),
            provider: LiveProviders::OPENAI,
        );
    }
}
