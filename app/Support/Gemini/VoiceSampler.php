<?php

namespace App\Support\Gemini;

use App\Support\PrivateStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extraits de voix pour la page « Essai des voix » : la même phrase de présentation,
 * dite par chaque voix avec le modèle de synthèse vocale (API Interactions).
 * Chaque extrait est gardé sur le disque privé : il n'est demandé à Google qu'une fois.
 *
 * Documentation : https://ai.google.dev/gemini-api/docs/speech-generation
 */
class VoiceSampler
{
    public const DIRECTORY = 'voice-samples';

    /**
     * Phrase et ton de l'extrait (config/teacher.php → voice_trial).
     *
     * @return array{text: string, style: string}
     */
    public static function script(): array
    {
        $replace = [':teacher' => (string) config('avatar.model.name'), ':platform' => (string) config('platform.name')];

        return [
            'text' => strtr((string) config('teacher.voice_trial.text'), $replace),
            'style' => (string) config('teacher.voice_trial.style'),
        ];
    }

    /**
     * Chemin de l'extrait : change si la voix, le modèle, la phrase ou le ton changent.
     */
    public static function path(string $voice): string
    {
        $script = self::script();
        $hash = substr(sha1(implode('|', [config('services.gemini.tts_model'), $voice, $script['text'], $script['style']])), 0, 16);

        return self::DIRECTORY.'/'.$voice.'-'.$hash.'.wav';
    }

    public function cached(string $voice): ?string
    {
        $disk = PrivateStorage::disk();

        return $disk->exists(self::path($voice)) ? $disk->get(self::path($voice)) : null;
    }

    /**
     * Demande l'extrait à Google et le garde. Renvoie le fichier WAV.
     *
     * @throws GeminiUnavailable
     */
    public function generate(string $voice): string
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new GeminiUnavailable('GEMINI_API_KEY manquante dans .env.');
        }

        $script = self::script();

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(30)
                ->post(rtrim(config('services.gemini.base_url'), '/').'/'.config('services.gemini.text_api_version').'/interactions', [
                    'model' => config('services.gemini.tts_model'),
                    'input' => [[
                        'type' => 'user_input',
                        'content' => [[
                            'type' => 'text',
                            'text' => $script['text'],
                            'annotations' => [['type' => 'speech_metadata', 'style' => $script['style']]],
                        ]],
                    ]],
                    'response_format' => ['type' => 'audio'],
                    'generation_config' => ['speech_config' => [['voice' => $voice]]],
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            throw new GeminiUnavailable('Connexion à l\'API Gemini impossible : '.$e->getMessage());
        }

        if ($response->status() === 429) {
            throw new GeminiUnavailable('Quota Gemini atteint.', quotaExceeded: true);
        }

        $audio = self::outputAudio($response->json('steps', []));

        if ($response->failed() || $audio === null) {
            Log::warning('Gemini : extrait de voix refusé.', ['status' => $response->status(), 'voice' => $voice]);

            throw new GeminiUnavailable('Extrait de voix refusé (HTTP '.$response->status().').');
        }

        $wav = base64_decode($audio, true) ?: '';

        if (! str_starts_with($wav, 'RIFF')) {
            throw new GeminiUnavailable('Extrait de voix illisible.');
        }

        PrivateStorage::disk()->put(self::path($voice), $wav);

        return $wav;
    }

    /**
     * Dernier bloc audio de la réponse (steps[].content[] de type « audio »).
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    public static function outputAudio(array $steps): ?string
    {
        $audio = null;

        foreach ($steps as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $item) {
                if (($item['type'] ?? null) === 'audio' && is_string($item['data'] ?? null)) {
                    $audio = $item['data'];
                }
            }
        }

        return $audio;
    }
}
