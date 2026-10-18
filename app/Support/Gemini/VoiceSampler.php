<?php

namespace App\Support\Gemini;

use App\Support\PrivateStorage;
use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechUsage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        $script = self::script();
        $label = 'Essai des voix : '.$voice;
        $wav = $this->synthesize($voice, $script['text'], $script['style'], $label);

        self::store(self::path($voice), $wav, $label);

        return $wav;
    }

    /**
     * Fait dire un texte par une voix avec le modèle de synthèse vocale. Renvoie le fichier WAV
     * (aussi utilisé pour le message vocal du résultat et le message de bienvenue). Chaque échec est
     * journalisé avec sa cause (SpeechLog) : code HTTP, type d'erreur, durée, morceau concerné ($label) ;
     * chaque réussite est comptée dans la consommation (SpeechUsage).
     *
     * @param  int|null  $timeout  délai d'attente en secondes (services.gemini.tts_timeout par défaut)
     *
     * @throws SpeechFailed
     */
    public function synthesize(string $voice, string $text, string $style, string $label = 'Synthèse vocale', ?int $timeout = null): string
    {
        try {
            $wav = $this->request($voice, $text, $style, max(1, $timeout ?? (int) config('services.gemini.tts_timeout')));
        } catch (SpeechFailed $e) {
            SpeechLog::failure($e, $label);

            throw $e;
        }

        SpeechUsage::record(app(GeminiSpeech::class), $label, $text, $wav, $voice);

        return $wav;
    }

    /**
     * Garde un fichier audio sur le disque privé. Un échec d'écriture (droits, bucket) est journalisé.
     *
     * @throws SpeechFailed
     */
    public static function store(string $path, string $wav, string $label, ?SpeechProvider $provider = null): void
    {
        try {
            $written = PrivateStorage::disk()->put($path, $wav);
        } catch (\Throwable $e) {
            $written = false;
            $cause = class_basename($e);
        }

        if ($written === false) {
            $failure = new SpeechFailed(SpeechFailed::STORAGE, 'Écriture refusée par le disque « '.PrivateStorage::name().' »'.(isset($cause) ? ' ('.$cause.')' : '').'.');
            SpeechLog::failure($failure, $label, $provider);

            throw $failure;
        }
    }

    private function request(string $voice, string $text, string $style, int $timeout): string
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new SpeechFailed(SpeechFailed::MISSING_KEY, 'GEMINI_API_KEY manquante dans les variables d\'environnement.');
        }

        $started = hrtime(true);
        $elapsed = fn () => (int) round((hrtime(true) - $started) / 1e6);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout($timeout)
                ->post(rtrim(config('services.gemini.base_url'), '/').'/'.config('services.gemini.text_api_version').'/interactions', [
                    'model' => config('services.gemini.tts_model'),
                    'input' => [[
                        'type' => 'user_input',
                        'content' => [[
                            'type' => 'text',
                            'text' => $text,
                            'annotations' => [['type' => 'speech_metadata', 'style' => $style]],
                        ]],
                    ]],
                    'response_format' => ['type' => 'audio'],
                    'generation_config' => ['speech_config' => [['voice' => $voice]]],
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

            throw new SpeechFailed(
                $timedOut ? SpeechFailed::TIMEOUT : SpeechFailed::CONNECTION,
                $timedOut ? "Pas de réponse de Google en {$timeout} secondes." : 'Connexion à l\'API Gemini impossible.',
                durationMs: $elapsed(),
            );
        }

        if ($response->failed()) {
            throw self::httpFailure($response, $elapsed());
        }

        $audio = self::outputAudioItem($response->json('steps', []));

        if ($audio === null) {
            throw new SpeechFailed(SpeechFailed::NO_AUDIO, 'Google a répondu sans audio.', $response->status(), $elapsed());
        }

        $wav = self::toWav(base64_decode($audio['data'], true) ?: '', $audio['mime_type']);

        if ($wav === null) {
            throw new SpeechFailed(SpeechFailed::UNREADABLE, 'Audio reçu illisible (type « '.($audio['mime_type'] ?: 'inconnu').' »).', $response->status(), $elapsed());
        }

        return $wav;
    }

    /**
     * Cause d'un refus de Google : 429 (par minute ou par jour, avec le délai conseillé), sinon le code HTTP
     * et le message d'erreur de Google (jamais la requête, donc jamais la clé).
     */
    private static function httpFailure(Response $response, int $durationMs): SpeechFailed
    {
        $error = (array) $response->json('error', []);
        $message = Str::limit(trim((string) ($error['message'] ?? '')), 240);
        $retryAfter = null;
        $daily = false;

        foreach ((array) ($error['details'] ?? []) as $detail) {
            if (preg_match('/^(\d+)(?:\.\d+)?s$/', (string) ($detail['retryDelay'] ?? ''), $match)) {
                $retryAfter = (int) $match[1];
            }

            foreach ((array) ($detail['violations'] ?? []) as $violation) {
                $daily = $daily || stripos((string) ($violation['quotaId'] ?? '').' '.($violation['quotaMetric'] ?? ''), 'PerDay') !== false;
            }
        }

        if ($response->status() === 429) {
            $daily = $daily || stripos($message, 'per day') !== false;
            $retryAfter ??= is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;

            return new SpeechFailed(
                $daily ? SpeechFailed::QUOTA_DAILY : SpeechFailed::QUOTA,
                $message !== '' ? $message : 'Quota Gemini atteint.',
                429,
                $durationMs,
                $retryAfter,
            );
        }

        return new SpeechFailed(
            SpeechFailed::HTTP,
            'HTTP '.$response->status().($message !== '' ? ' : '.$message : '').' (modèle « '.config('services.gemini.tts_model').' »).',
            $response->status(),
            $durationMs,
        );
    }

    /**
     * Fichier WAV à partir de l'audio reçu : gardé tel quel s'il a déjà un en-tête WAV (« RIFF ») ; PCM brut
     * (audio/l16 ou audio/pcm, sans en-tête : 16 bits, mono, 24 kHz sauf mention « rate= ») enveloppé dans un
     * en-tête WAV ; null s'il est vide ou d'un autre format.
     */
    public static function toWav(string $bytes, string $mimeType = ''): ?string
    {
        if ($bytes === '') {
            return null;
        }

        if (str_starts_with($bytes, 'RIFF')) {
            return $bytes;
        }

        $mime = strtolower($mimeType);

        if ($mime !== '' && ! str_contains($mime, 'l16') && ! str_contains($mime, 'pcm')) {
            return null;
        }

        $rate = preg_match('/rate=(\d+)/', $mime, $match) ? (int) $match[1] : 24000;
        $bytes = strlen($bytes) % 2 === 0 ? $bytes : substr($bytes, 0, -1);
        $length = strlen($bytes);

        return 'RIFF'.pack('V', 36 + $length).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', $rate).pack('V', $rate * 2).pack('v', 2).pack('v', 16)
            .'data'.pack('V', $length).$bytes;
    }

    /**
     * Dernier bloc audio de la réponse (steps[].content[] de type « audio ») : données en base64 et type.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array{data: string, mime_type: string}|null
     */
    public static function outputAudioItem(array $steps): ?array
    {
        $audio = null;

        foreach ($steps as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $item) {
                if (($item['type'] ?? null) === 'audio' && is_string($item['data'] ?? null)) {
                    $audio = ['data' => $item['data'], 'mime_type' => (string) ($item['mime_type'] ?? $item['mimeType'] ?? '')];
                }
            }
        }

        return $audio;
    }
}
