<?php

namespace App\Support\Live;

use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\LiveClient;
use App\Support\Gemini\LiveConnection;
use App\Support\Gemini\VoiceSampler;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teacher;
use Illuminate\Support\Facades\Cache;

/**
 * « Écouter en séance » (« Essai des voix ») : la phrase d'essai dite par une VRAIE connexion de séance (Gemini Live
 * ou OpenAI Realtime) avec cette voix, sans micro. Le navigateur reçoit la voix, la joue et l'envoie ici pour la
 * garder : les écoutes suivantes ne se connectent plus. Rien n'est enregistré comme séance : aucune minute ni temps
 * du jour d'apprenant ; l'appel OpenAI est raccroché au plus tard après « SECONDS » secondes (live:close-stale),
 * le jeton Gemini expire au même moment.
 */
class LiveVoiceSample
{
    public const SECONDS = 30;

    /** Formats gardés : WAV (Gemini, recomposé dans le navigateur) ou WebM (OpenAI, enregistré par le navigateur). */
    public const TYPES = ['wav' => 'audio/wav', 'webm' => 'audio/webm'];

    /** Consigne : la phrase, une fois, sans rien d'autre. */
    public static function instructions(Teacher $teacher): string
    {
        $phrase = VoiceSampler::script($teacher)['text'];

        return 'You are '.$teacher->name().', an English teacher at '.config('platform.name').'. This is a voice check, not a lesson. '
            .'When you receive the word START, say EXACTLY the following text, once, in your natural, warm teaching voice, with American English pronunciation, '
            .'then stop and say nothing else: "'.$phrase.'"';
    }

    /**
     * Connexion pour cette voix : Gemini (jeton éphémère de « SECONDS » secondes, clé principale puis deuxième clé)
     * ou OpenAI (adresse d'appel à usage unique).
     *
     * @return array<string, mixed>
     *
     * @throws GeminiUnavailable
     */
    public static function connection(string $provider, string $voice, Teacher $teacher, int $userId): array
    {
        $instructions = self::instructions($teacher);

        if ($provider === LiveProviders::OPENAI) {
            if (! LiveProviders::hasKey(LiveProviders::OPENAI)) {
                throw new GeminiUnavailable('Clé OPENAI_API_KEY absente.');
            }

            $session = [
                'type' => 'realtime',
                'model' => (string) config('services.openai.realtime_model'),
                'instructions' => $instructions,
                'output_modalities' => ['audio'],
                // Sans micro : aucune détection de parole, la réponse est demandée par le navigateur.
                'audio' => ['input' => ['turn_detection' => null], 'output' => ['voice' => $voice]],
            ];

            return ['provider' => $provider, 'call_url' => LiveConnection::callUrl(null, $session, $userId, self::SECONDS)];
        }

        $setup = [
            'model' => 'models/'.config('services.gemini.live_model'),
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]]],
            ],
            'systemInstruction' => ['parts' => [['text' => $instructions]]],
        ];
        $keys = array_values(array_filter([LiveProviders::GEMINI, LiveProviders::GEMINI2], fn (string $name) => LiveProviders::hasKey($name)));

        if ($keys === []) {
            throw new GeminiUnavailable('Clé GEMINI_API_KEY absente.');
        }

        // Clé principale, puis la deuxième clé Gemini en secours (autre projet Google, autre limite).
        foreach ($keys as $index => $name) {
            try {
                $token = app(LiveClient::class)->createToken($setup, now()->addSeconds(self::SECONDS), LiveProviders::geminiKey($name));

                return ['provider' => $provider, 'websocket_url' => $token['websocket_url'], 'setup' => $setup];
            } catch (GeminiUnavailable $e) {
                LiveLog::failure($name, 'Essai des voix : écouter en séance', $e->getMessage(), $e->httpStatus, $e->quotaExceeded);

                if ($index === array_key_last($keys)) {
                    throw $e;
                }
            }
        }

        throw new GeminiUnavailable('Aucune clé Gemini.');
    }

    /** Fichier gardé (sans extension) : change si la voix, le modèle, le professeur ou la phrase changent. */
    public static function basePath(string $provider, string $voice, Teacher $teacher): string
    {
        $model = $provider === LiveProviders::OPENAI ? config('services.openai.realtime_model') : config('services.gemini.live_model');
        $hash = substr(sha1(implode('|', [$provider, $model, $voice, $teacher->key, self::instructions($teacher)])), 0, 16);

        return VoiceSampler::DIRECTORY.'/live-'.$provider.'-'.$teacher->key.'-'.$voice.'-'.$hash;
    }

    /**
     * Extrait déjà gardé : chemin et type, ou null.
     *
     * @return array{path: string, type: string}|null
     */
    public static function stored(string $provider, string $voice, Teacher $teacher): ?array
    {
        $disk = PrivateStorage::disk();

        foreach (self::TYPES as $extension => $type) {
            if ($disk->exists($path = self::basePath($provider, $voice, $teacher).'.'.$extension)) {
                return ['path' => $path, 'type' => $type];
            }
        }

        return null;
    }

    /** Essai en cours de cet administrateur (pour accepter l'extrait qu'il renvoie). */
    public static function start(int $userId, string $provider, string $voice, Teacher $teacher): void
    {
        Cache::put(self::key($userId), ['provider' => $provider, 'voice' => $voice, 'teacher' => $teacher->key], now()->addMinutes(3));
    }

    public static function matches(int $userId, string $provider, string $voice, Teacher $teacher): bool
    {
        $current = Cache::get(self::key($userId));

        return is_array($current) && $current === ['provider' => $provider, 'voice' => $voice, 'teacher' => $teacher->key];
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    private static function key(int $userId): string
    {
        return 'voice-live-sample:'.$userId;
    }
}
