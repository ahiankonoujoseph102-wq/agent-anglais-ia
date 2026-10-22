<?php

namespace App\Support\Videos;

use App\Support\Gemini\SpeechFailed;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechProviders;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Essai comparatif des voix des vidéos (page « Vidéos ») : le même passage, en français avec des phrases en anglais,
 * dit par chaque voix de config('videos.voice_trial.voices'), avec le ton des vidéos. Chaque extrait est préparé au
 * premier toucher sur « Écouter », puis gardé sur le disque privé.
 */
class VideoVoiceTrial
{
    /**
     * @return list<array{provider: string, voice: string, label: string, available: bool, message: ?string, chosen: bool, ready: bool}>
     */
    public static function voices(): array
    {
        $chosen = VideoSettings::voice();
        $list = [];

        foreach ((array) config('videos.voice_trial.voices') as $name => $voices) {
            $provider = SpeechProviders::find($name);

            foreach ($provider !== null ? (array) $voices : [] as $voice) {
                $list[] = [
                    'provider' => $name,
                    'voice' => $voice,
                    'label' => $provider->label(),
                    'available' => $provider->hasKey(),
                    'message' => $provider->hasKey() ? null : 'Clé '.$provider->keyVariable().' absente dans les variables de Laravel Cloud : cette voix ne peut pas être essayée.',
                    'chosen' => $chosen === ['provider' => $name, 'voice' => $voice],
                    'ready' => PrivateStorage::disk()->exists(self::path($name, $voice)),
                ];
            }
        }

        return $list;
    }

    public static function isAllowed(string $provider, string $voice): bool
    {
        return in_array($voice, (array) config("videos.voice_trial.voices.{$provider}", []), true);
    }

    /** Fichier de l'extrait : change avec le texte, le ton, le modèle et les réglages de la voix. */
    public static function path(string $provider, string $voice): string
    {
        $speech = SpeechProviders::find($provider);
        $hash = substr(md5(implode('|', [config('videos.voice_trial.text'), config('videos.voice.style'), $speech?->model(), $speech?->settings()])), 0, 12);

        return "video-voice-trials/{$provider}-{$voice}-{$hash}.wav";
    }

    /**
     * L'extrait WAV (préparé s'il manque).
     *
     * @throws SpeechFailed
     */
    public static function sample(string $provider, string $voice, string $limiterKey): string
    {
        $disk = PrivateStorage::disk();
        $path = self::path($provider, $voice);

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $speech = SpeechProviders::find($provider);

        if ($speech === null || ! $speech->hasKey()) {
            throw new SpeechFailed(SpeechFailed::MISSING_KEY, 'Clé absente.');
        }

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('videos.voice_trial.per_hour'))) {
            throw new SpeechFailed(SpeechFailed::QUOTA, 'Trop d\'essais pour cette heure.');
        }

        RateLimiter::hit($limiterKey, 3600);
        $wav = app(VideoVoice::class)->synthesize($speech, $voice, (string) config('videos.voice_trial.text'), 'Essai des voix des vidéos');
        $disk->put($path, $wav);

        return $wav;
    }
}
