<?php

namespace App\Support\Gemini;

use App\Support\PrivateStorage;
use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\Loudness;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\SpeechUsage;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use App\Support\Videos\VideoVoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
     * Phrase et ton de l'extrait (config/teacher.php → voice_trial), au nom et avec le ton du professeur
     * (Teacher Joe par défaut).
     *
     * @return array{text: string, style: string}
     */
    public static function script(?Teacher $teacher = null): array
    {
        $teacher ??= Teachers::default();
        $replace = [':teacher' => $teacher->name(), ':platform' => (string) config('platform.name')];

        return [
            'text' => strtr((string) config('teacher.voice_trial.text'), $replace),
            'style' => $teacher->sampleStyle(),
        ];
    }

    /**
     * Chemin de l'extrait : change si la voix, le modèle, la phrase ou le ton changent.
     */
    public static function path(string $voice, ?Teacher $teacher = null): string
    {
        $script = self::script($teacher);
        $hash = substr(sha1(implode('|', [config('services.gemini.tts_model'), $voice, $script['text'], $script['style']])), 0, 16);

        return self::DIRECTORY.'/'.$voice.'-'.$hash.'.wav';
    }

    /** Extrait d'une voix OpenAI (même phrase, même ton) : change avec le modèle et les réglages de la synthèse. */
    public static function openAiPath(string $voice, ?Teacher $teacher = null): string
    {
        $script = self::script($teacher);
        $speech = SpeechProviders::find('openai');
        $hash = substr(sha1(implode('|', [$speech?->model(), $speech?->settings(), $voice, $script['text'], $script['style']])), 0, 16);

        return self::DIRECTORY.'/openai-'.$voice.'-'.$hash.'.wav';
    }

    public function cached(string $voice, ?Teacher $teacher = null, string $provider = 'gemini'): ?string
    {
        $disk = PrivateStorage::disk();
        $path = $provider === 'openai' ? self::openAiPath($voice, $teacher) : self::path($voice, $teacher);

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    /**
     * Demande l'extrait à Google et le garde. Renvoie le fichier WAV.
     *
     * @throws GeminiUnavailable
     */
    public function generate(string $voice, ?Teacher $teacher = null): string
    {
        $script = self::script($teacher);
        $label = 'Essai des voix : '.$voice;
        $wav = $this->synthesize($voice, $script['text'], $script['style'], $label);

        return self::store(self::path($voice, $teacher), $wav, $label);
    }

    /**
     * Extrait d'une voix OpenAI pour « Essai des voix » (voix des séances en direct chez OpenAI) : produit par la
     * synthèse vocale d'OpenAI avec la même voix, puis gardé.
     *
     * @throws SpeechFailed
     */
    public function generateOpenAi(string $voice, ?Teacher $teacher = null): string
    {
        $speech = SpeechProviders::find('openai');

        if ($speech === null || ! $speech->hasKey()) {
            throw new SpeechFailed(SpeechFailed::MISSING_KEY, 'Clé absente.');
        }

        $script = self::script($teacher);
        $label = 'Essai des voix : '.$voice;
        $wav = app(VideoVoice::class)->synthesize($speech, $voice, $script['text'], $label, $script['style']);

        return self::store(self::openAiPath($voice, $teacher), $wav, $label, $speech);
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
        $timeout = max(1, $timeout ?? (int) config('services.gemini.tts_timeout'));
        $keys = self::keys();
        $failure = null;

        // Clé principale, puis la deuxième clé Gemini (GEMINI_API_KEY_2, autre projet Google, autre limite) en
        // secours : limite du jour ou par minute, service surchargé (503), délai, connexion, clé refusée. Une clé
        // déjà à sa limite du jour n'est plus essayée avant le lendemain (aucune demande perdue).
        foreach ($keys as $index => $key) {
            $last = $index === array_key_last($keys);

            if (! $last && Cache::has(self::exhaustedKey($index))) {
                continue;
            }

            try {
                $wav = $this->request($voice, $text, $style, $timeout, $key);
            } catch (SpeechFailed $e) {
                SpeechLog::failure($e, $label.(count($keys) > 1 ? ($index === 0 ? ' (clé principale)' : ' (deuxième clé)') : ''));
                $failure = $e;

                if ($e->kind === SpeechFailed::QUOTA_DAILY) {
                    Cache::put(self::exhaustedKey($index), true, now()->endOfDay());
                }

                if ($last || ! self::switchesKey($e)) {
                    throw $e;
                }

                continue;
            }

            SpeechUsage::record(app(GeminiSpeech::class), $label, $text, $wav, $voice);

            return $wav;
        }

        throw $failure ?? new SpeechFailed(SpeechFailed::MISSING_KEY, 'GEMINI_API_KEY manquante dans les variables d\'environnement.');
    }

    /**
     * Clés Gemini de la synthèse vocale, dans l'ordre : principale (GEMINI_API_KEY), puis la deuxième
     * (GEMINI_API_KEY_2) si elle est réglée. Liste vide : aucune clé.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_values(array_filter([config('services.gemini.key'), config('services.gemini.key_2')], fn ($key) => filled($key)));
    }

    /** Échec qui vaut la peine d'essayer l'autre clé (autre projet Google, autre limite). */
    private static function switchesKey(SpeechFailed $e): bool
    {
        return in_array($e->kind, [SpeechFailed::QUOTA, SpeechFailed::QUOTA_DAILY, SpeechFailed::TIMEOUT, SpeechFailed::CONNECTION, SpeechFailed::NO_AUDIO], true)
            || ($e->kind === SpeechFailed::HTTP && ($e->status === null || $e->status >= 500 || in_array($e->status, [400, 401, 403], true)));
    }

    private static function exhaustedKey(int $index): string
    {
        return 'gemini-tts-key-exhausted:'.$index;
    }

    /**
     * Garde un fichier audio sur le disque privé et le renvoie tel qu'il est gardé (volume harmonisé). Un échec
     * d'écriture (droits, bucket) est journalisé.
     *
     * @throws SpeechFailed
     */
    public static function store(string $path, string $wav, string $label, ?SpeechProvider $provider = null): string
    {
        // Même volume pour tous les messages : crête à −1 décibel.
        $wav = Loudness::normalize($wav);

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

        return $wav;
    }

    private function request(string $voice, string $text, string $style, int $timeout, ?string $key = null): string
    {
        $key ??= config('services.gemini.key');

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
