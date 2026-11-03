<?php

namespace App\Support\Practice;

use App\Support\Admin\ViewAs;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Speech\VoiceMessage;
use App\Support\Teachers\Teacher;
use App\Support\Videos\VoiceSplitter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * « Réviser à voix haute » : la voix des éléments du tableau d'une séance du jour (phrases clés, mots, verbes).
 *
 * - Chaque élément est un fichier WAV gardé sur le disque privé, nommé d'après son TEXTE, la voix et le modèle :
 *   une même phrase n'est demandée qu'une fois, pour tous les élèves et tous les tableaux qui la contiennent.
 * - Préparés en peu de demandes (plusieurs éléments par demande, audio recoupé au silence : VoiceMessage::prepareGroup),
 *   après la réponse HTTP (queue), jamais pendant l'écoute ; un tableau entier est dit par UN fournisseur et une voix.
 * - Voix du professeur de l'élève (Teacher Joe, ou Teacher Rose avec la voix de ses messages).
 * - Rien de prêt : la page utilise la voix anglaise du téléphone ; elle reste utilisable.
 */
final class PracticeAudio
{
    public const DIRECTORY = 'practice-audio';

    /**
     * Éléments à réviser d'un tableau : phrases clés, puis mots, puis verbes (dans cet ordre, sans doublon).
     *
     * @param  array{phrases?: list<array{en: string, fr: ?string, use?: ?string}>, vocabulary?: list<array{en: string, fr: ?string}>, verbs?: list<array{forms: string, fr: ?string}>}  $board
     * @return list<array{kind: string, en: string, say: string, fr: ?string}>
     */
    public static function items(array $board): array
    {
        $items = [];
        $seen = [];
        $add = function (string $kind, string $en, string $say, ?string $fr) use (&$items, &$seen) {
            $en = trim($en);
            $say = trim(preg_replace('/\s+/u', ' ', $say) ?? '');
            $key = mb_strtolower($say);

            if ($en === '' || $say === '' || isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $items[] = ['kind' => $kind, 'en' => $en, 'say' => $say, 'fr' => ($fr = trim((string) $fr)) === '' ? null : $fr];
        };

        foreach ((array) ($board['phrases'] ?? []) as $phrase) {
            $add('phrase', (string) ($phrase['en'] ?? ''), (string) ($phrase['en'] ?? ''), $phrase['fr'] ?? ($phrase['use'] ?? null));
        }

        foreach ((array) ($board['vocabulary'] ?? []) as $word) {
            $add('word', (string) ($word['en'] ?? ''), (string) ($word['en'] ?? ''), $word['fr'] ?? null);
        }

        foreach ((array) ($board['verbs'] ?? []) as $verb) {
            $forms = (string) ($verb['forms'] ?? '');
            // « go / went / gone » est dit « go, went, gone » (une petite pause entre les formes).
            $add('verb', $forms, str_replace(['/', ' ,'], [',', ','], $forms), $verb['fr'] ?? null);
        }

        return array_slice($items, 0, max(1, (int) config('platform.practice.max_items', 14)));
    }

    public static function enabled(): bool
    {
        return (bool) config('platform.practice.enabled', true);
    }

    /** Ton demandé au fournisseur, accordé au professeur (Teacher Rose : au féminin). */
    public static function style(Teacher $teacher): string
    {
        return $teacher->speechStyle((string) config('platform.practice.style'));
    }

    /**
     * Le « message » vocal d'un tableau : un morceau par élément, un fichier par texte.
     *
     * @param  list<array{kind: string, en: string, say: string, fr: ?string}>  $items
     */
    public static function message(array $items, Teacher $teacher): VoiceMessage
    {
        $texts = array_map(fn (array $item) => $item['say'], $items);
        $style = self::style($teacher);

        return new VoiceMessage(
            'practice:'.$teacher->key.':'.sha1(implode("\n", $texts)),
            'Réviser à voix haute',
            array_map(fn (array $item) => ['label' => 'Réviser à voix haute : '.mb_substr($item['say'], 0, 60), 'text' => $item['say']], $items),
            $style,
            fn (string $text, SpeechProvider $provider) => self::path($text, $provider, $style),
            $teacher->isDefault() ? null : $teacher,
        );
    }

    /**
     * Fichier d'un texte chez un fournisseur : change si le texte, la voix, le modèle, le ton ou les réglages changent.
     */
    public static function path(string $text, SpeechProvider $provider, string $style): string
    {
        $hash = substr(sha1(implode('|', [$provider->model(), $provider->voice(), $text, $style, $provider->settings()])), 0, 20);

        return self::DIRECTORY.'/'.$provider->name().'-'.$provider->voice().'-'.$hash.'.wav';
    }

    /**
     * État de la voix : le fournisseur qui la jouera et, pour chaque élément, si son fichier est prêt.
     *
     * @return array{provider: ?SpeechProvider, ready: list<bool>}
     */
    public static function status(VoiceMessage $message): array
    {
        try {
            $files = $message->files();
            $provider = $message->provider($files);
        } catch (Throwable) {
            return ['provider' => null, 'ready' => array_fill(0, count($message->pieces), false)];
        }

        return [
            'provider' => $provider,
            'ready' => array_map(
                fn (array $piece) => $provider !== null && isset($files[$message->path($piece['text'], $provider)]),
                $message->pieces,
            ),
        ];
    }

    /** Fichier WAV d'un élément s'il est déjà prêt (jamais de synthèse ici). */
    public static function file(VoiceMessage $message, int $index): ?string
    {
        $piece = $message->pieces[$index] ?? null;
        $provider = $piece !== null ? self::status($message)['provider'] : null;

        if ($provider === null) {
            return null;
        }

        try {
            $disk = PrivateStorage::disk();
            $path = $message->path($piece['text'], $provider);

            return $disk->exists($path) ? $disk->get($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Fait préparer la voix manquante APRÈS la réponse (ouverture de la page, demande d'état). Rien si tout est prêt,
     * si aucun fournisseur n'a de clé ou après un échec récent.
     */
    public static function queue(VoiceMessage $message): void
    {
        // Administrateur qui regarde l'espace d'un élève (lecture seule) : jamais d'appel à un fournisseur.
        if (! self::enabled() || ViewAs::active() || SpeechProviders::available($message->teacher) === [] || Cache::has(self::failedKey($message))) {
            return;
        }

        $status = self::status($message);

        if ($status['ready'] !== [] && ! in_array(false, $status['ready'], true)) {
            return;
        }

        defer(static function () use ($message) {
            try {
                self::prepare($message);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Prépare les éléments manquants : chez le fournisseur qui a déjà une partie du tableau (une seule voix), sinon le
     * premier qui a une clé ; s'il échoue, le tableau ENTIER chez le suivant. Renvoie vrai si tout est prêt.
     */
    public static function prepare(VoiceMessage $message): bool
    {
        $failed = self::failedKey($message);

        if (Cache::has($failed)) {
            return false;
        }

        try {
            return Cache::lock('practice-audio:'.$message->key, 300)->block(1, function () use ($message, $failed) {
                $files = $message->files();
                $chosen = $message->provider($files);
                $providers = SpeechProviders::available($message->teacher);

                if ($chosen !== null && $chosen->hasKey()) {
                    $providers = array_values(array_filter($providers, fn (SpeechProvider $provider) => $provider->name() !== $chosen->name()));
                    array_unshift($providers, $chosen);
                }

                foreach ($providers as $provider) {
                    $missing = $message->missing($provider, $files);

                    if ($missing === []) {
                        return true;
                    }

                    try {
                        $texts = array_map(fn (array $piece) => $piece['text'], $missing);

                        foreach (VoiceSplitter::groups($texts, (int) config('platform.practice.group_characters', 1500)) as $group) {
                            $message->prepareGroup(array_map(fn (int $index) => $missing[$index], $group), $provider);
                        }

                        return true;
                    } catch (GeminiUnavailable) {
                        // Cause déjà journalisée par le fournisseur ; le suivant dit tout le tableau.
                        continue;
                    }
                }

                Cache::put($failed, true, (int) config('platform.practice.retry_after', 600));

                return false;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private static function failedKey(VoiceMessage $message): string
    {
        return 'practice-audio-failed:'.$message->key;
    }
}
