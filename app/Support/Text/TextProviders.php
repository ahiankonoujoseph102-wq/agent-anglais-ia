<?php

namespace App\Support\Text;

use Illuminate\Support\Facades\Cache;

/**
 * Les fournisseurs du modèle texte, dans l'ordre de config/services.php → text.providers (TEXT_PROVIDERS,
 * « gemini,openai » par défaut). Chaque demande est essayée chez le premier ; s'il échoue (limite 429, limite du
 * jour, erreur, trop lent), LA MÊME demande est refaite chez le suivant. Un fournisseur sans clé est ignoré.
 * Après une limite ou une clé refusée, le fournisseur est mis de côté quelques minutes (text.pause_seconds) :
 * les demandes suivantes vont directement au suivant, sans attendre un nouveau refus.
 */
final class TextProviders
{
    private const CLASSES = [
        GeminiText::NAME => GeminiText::class,
        OpenAiText::NAME => OpenAiText::class,
    ];

    /**
     * @return list<TextProvider>
     */
    public static function all(): array
    {
        return array_map(fn (string $class) => app($class), array_values(self::CLASSES));
    }

    public static function find(?string $name): ?TextProvider
    {
        $class = self::CLASSES[strtolower((string) $name)] ?? null;

        return $class !== null ? app($class) : null;
    }

    /**
     * @return list<TextProvider>
     */
    public static function configured(): array
    {
        $names = array_unique(array_map('strtolower', (array) config('services.text.providers', [])));

        return array_values(array_filter(array_map(fn (string $name) => self::find($name), $names)));
    }

    /**
     * @return list<TextProvider>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::configured(), fn (TextProvider $provider) => $provider->hasKey()));
    }

    /**
     * Fait la demande chez le premier fournisseur qui répond. null si tous échouent (ou aucun n'a de clé) :
     * l'appelant garde alors son comportement de repli.
     *
     * @param  TextProvider|null  $only  un seul fournisseur, sans secours (bouton « Tester » du diagnostic)
     * @param  callable(TextResult): bool|null  $accept  la réponse est-elle utilisable ? Sinon, essai chez le suivant.
     */
    public static function generate(TextRequest $request, ?TextProvider $only = null, ?callable $accept = null): ?TextResult
    {
        $providers = $only !== null ? [$only] : self::ordered();

        foreach ($providers as $provider) {
            try {
                $result = $provider->generate($request);

                if ($accept !== null && ! $accept($result)) {
                    throw new TextFailed(TextFailed::EMPTY, $provider->label().' a répondu dans un format inattendu.', durationMs: $result->durationMs);
                }
            } catch (TextFailed $e) {
                TextLog::failure($e, $request, $provider);

                if ($e->pausesProvider() && $only === null) {
                    self::pause($provider);
                }

                continue;
            }

            TextUsage::record($result, $request);

            return $result;
        }

        return null;
    }

    /**
     * Fournisseurs avec une clé, ceux mis de côté à la fin (essayés quand même en dernier recours).
     *
     * @return list<TextProvider>
     */
    private static function ordered(): array
    {
        $ready = [];
        $paused = [];

        foreach (self::available() as $provider) {
            if (self::isPaused($provider)) {
                $paused[] = $provider;
            } else {
                $ready[] = $provider;
            }
        }

        return [...$ready, ...$paused];
    }

    public static function isPaused(TextProvider $provider): bool
    {
        try {
            return Cache::has('text-pause:'.$provider->name());
        } catch (\Throwable) {
            return false;
        }
    }

    private static function pause(TextProvider $provider): void
    {
        $seconds = (int) config('services.text.pause_seconds', 120);

        if ($seconds <= 0) {
            return;
        }

        try {
            Cache::put('text-pause:'.$provider->name(), true, now()->addSeconds($seconds));
        } catch (\Throwable) {
            // Sans cache, chaque demande essaie simplement le premier fournisseur.
        }
    }
}
