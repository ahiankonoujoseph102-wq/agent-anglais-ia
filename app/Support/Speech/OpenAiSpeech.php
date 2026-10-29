<?php

namespace App\Support\Speech;

use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\VoiceSampler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Synthèse vocale d'OpenAI, secours de Gemini pour les messages (POST /audio/speech). L'audio est demandé en
 * PCM brut (24 kHz, 16 bits, mono, d'après la documentation), puis enveloppé dans un en-tête WAV.
 *
 * Documentation : https://platform.openai.com/docs/guides/text-to-speech
 */
class OpenAiSpeech extends SpeechProvider
{
    public const NAME = 'openai';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function keyVariable(): string
    {
        return 'OPENAI_API_KEY';
    }

    public function hasKey(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function model(): string
    {
        return (string) config('services.openai.tts_model');
    }

    public function voice(): string
    {
        return $this->teacher?->openaiSpeechVoice() ?? (string) config('services.openai.tts_voice');
    }

    public function spacing(): int
    {
        return (int) config('services.openai.tts_spacing_seconds');
    }

    public function costPerMinute(): float
    {
        return (float) config('services.openai.tts_cost_per_minute');
    }

    public function synthesize(string $text, string $style, string $label = 'Synthèse vocale', ?int $timeout = null): string
    {
        try {
            $wav = $this->request($text, $style, max(1, $timeout ?? (int) config('services.gemini.tts_timeout')));
        } catch (SpeechFailed $e) {
            SpeechLog::failure($e, $label, $this);

            throw $e;
        }

        SpeechUsage::record($this, $label, $text, $wav);

        return $wav;
    }

    /** Vitesse de la voix des messages (services.openai.tts_speed, 0,25 à 4, 1 = normal), ou null. */
    public function speed(): ?float
    {
        $speed = config('services.openai.tts_speed');

        return is_numeric($speed) ? round(max(0.25, min(4.0, (float) $speed)), 2) : null;
    }

    public function settings(): string
    {
        return $this->speed() !== null ? 'speed='.$this->speed() : '';
    }

    private function request(string $text, string $style, int $timeout): string
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new SpeechFailed(SpeechFailed::MISSING_KEY, 'OPENAI_API_KEY manquante dans les variables d\'environnement.');
        }

        $started = hrtime(true);
        $elapsed = fn () => (int) round((hrtime(true) - $started) / 1e6);
        $body = ['model' => $this->model(), 'voice' => $this->voice(), 'input' => $text, 'response_format' => 'pcm'];

        // Débit calme des messages : réglage de vitesse d'OpenAI, en plus du ton (instructions).
        if (($speed = $this->speed()) !== null) {
            $body['speed'] = $speed;
        }

        // Les consignes de ton ne sont acceptées que par les modèles « gpt-… » (pas par tts-1).
        if ($style !== '' && str_starts_with($this->model(), 'gpt-')) {
            $body['instructions'] = $style;
        }

        try {
            $response = Http::withToken((string) $key)
                ->timeout($timeout)
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/audio/speech', $body);
        } catch (ConnectionException $e) {
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

            throw new SpeechFailed(
                $timedOut ? SpeechFailed::TIMEOUT : SpeechFailed::CONNECTION,
                $timedOut ? "Pas de réponse d'OpenAI en {$timeout} secondes." : 'Connexion à l\'API OpenAI impossible.',
                durationMs: $elapsed(),
            );
        }

        if ($response->failed()) {
            throw $this->httpFailure($response, $elapsed());
        }

        $type = strtolower((string) $response->header('Content-Type'));

        if (str_contains($type, 'json')) {
            throw new SpeechFailed(SpeechFailed::NO_AUDIO, 'OpenAI a répondu sans audio.', $response->status(), $elapsed());
        }

        $wav = VoiceSampler::toWav($response->body(), 'audio/pcm;rate=24000');

        if ($wav === null) {
            throw new SpeechFailed(SpeechFailed::NO_AUDIO, 'OpenAI a répondu sans audio.', $response->status(), $elapsed());
        }

        return $wav;
    }

    /**
     * Cause d'un refus d'OpenAI : crédit épuisé (comme une limite du jour : on passe au fournisseur suivant),
     * limite de demandes par minute, sinon le code HTTP et le message d'OpenAI (jamais la requête ni la clé).
     */
    private function httpFailure(Response $response, int $durationMs): SpeechFailed
    {
        $error = (array) $response->json('error', []);
        $message = Str::limit(trim((string) ($error['message'] ?? '')), 240);
        $code = (string) ($error['code'] ?? $error['type'] ?? '');

        if ($response->status() === 429) {
            $exhausted = in_array($code, ['insufficient_quota', 'billing_hard_limit_reached'], true);
            $retryAfter = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;

            return new SpeechFailed(
                $exhausted ? SpeechFailed::QUOTA_DAILY : SpeechFailed::QUOTA,
                $message !== '' ? $message : ($exhausted ? 'Crédit OpenAI épuisé.' : 'Limite de demandes OpenAI atteinte.'),
                429,
                $durationMs,
                $retryAfter,
            );
        }

        return new SpeechFailed(
            SpeechFailed::HTTP,
            'HTTP '.$response->status().($message !== '' ? ' : '.$message : '').' (modèle « '.$this->model().' », voix « '.$this->voice().' »).',
            $response->status(),
            $durationMs,
        );
    }
}
