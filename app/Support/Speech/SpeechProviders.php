<?php

namespace App\Support\Speech;

use App\Support\Teachers\Teacher;

/**
 * Les fournisseurs de synthèse vocale, dans l'ordre de config/services.php → tts.providers (TTS_PROVIDERS,
 * « gemini,openai » par défaut) : le premier d'abord, le suivant en secours. Un nom inconnu est ignoré ;
 * un fournisseur sans clé n'est jamais appelé (OpenAI sans OPENAI_API_KEY : simplement ignoré).
 */
final class SpeechProviders
{
    /** Fournisseurs connus. */
    private const CLASSES = [
        GeminiSpeech::NAME => GeminiSpeech::class,
        OpenAiSpeech::NAME => OpenAiSpeech::class,
    ];

    /**
     * Tous les fournisseurs connus, même absents de l'ordre (leurs fichiers restent valables), avec la voix de
     * ce professeur (null : professeur par défaut).
     *
     * @return list<SpeechProvider>
     */
    public static function all(?Teacher $teacher = null): array
    {
        return array_map(fn (string $class) => app($class)->forTeacher($teacher), array_values(self::CLASSES));
    }

    public static function find(?string $name, ?Teacher $teacher = null): ?SpeechProvider
    {
        $class = self::CLASSES[strtolower((string) $name)] ?? null;

        return $class !== null ? app($class)->forTeacher($teacher) : null;
    }

    /**
     * Fournisseurs de l'ordre réglé, sans doublon.
     *
     * @return list<SpeechProvider>
     */
    public static function configured(?Teacher $teacher = null): array
    {
        $names = array_unique(array_map('strtolower', (array) config('services.tts.providers', [])));

        return array_values(array_filter(array_map(fn (string $name) => self::find($name, $teacher), $names)));
    }

    /**
     * Fournisseurs de l'ordre réglé qui ont une clé : les seuls à qui l'on demande une synthèse.
     *
     * @return list<SpeechProvider>
     */
    public static function available(?Teacher $teacher = null): array
    {
        return array_values(array_filter(self::configured($teacher), fn (SpeechProvider $provider) => $provider->hasKey()));
    }

    /**
     * LE fournisseur d'un message (une seule voix par message) : le premier, dans l'ordre réglé, chez qui tout
     * le message est prêt ; sinon celui qui en a le plus de morceaux prêts (à égalité : celui qui a une clé, puis
     * l'ordre) ; null si aucun fournisseur n'est réglé.
     *
     * @param  array<string, true>|null  $files  fichiers déjà listés (SpeechFiles::index)
     */
    public static function choose(VoiceMessage $message, ?array $files = null): ?SpeechProvider
    {
        $files ??= $message->files();
        $total = count($message->pieces);
        $best = null;
        $bestScore = null;

        foreach (self::configured($message->teacher) as $position => $provider) {
            $ready = $message->readyCount($provider, $files);

            if ($total > 0 && $ready === $total) {
                return $provider;
            }

            $score = [$ready, $provider->hasKey() ? 1 : 0, -$position];

            if ($bestScore === null || $score > $bestScore) {
                [$best, $bestScore] = [$provider, $score];
            }
        }

        return $best;
    }
}
