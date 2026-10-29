<?php

namespace App\Support\Videos;

/**
 * Fin de CHAQUE vidéo, toujours dans cet ordre, dite et sous-titrée : (a) la question du jour, à laquelle on répond en
 * une phrase en commentaire ; (b) une phrase pour s'abonner, aimer et partager ; (c) l'action principale : évaluer
 * son niveau gratuitement sur le site, adresse affichée en gros et dite à voix haute. La question vient du script ;
 * le reste vient de la configuration et du réglage de l'adresse du site.
 */
class VideoEnding
{
    public const QUESTION = 'question';

    public const SUBSCRIBE = 'subscribe';

    public const CLOSING = 'closing';

    /**
     * Les trois parties de la fin, ou null si l'adresse du site n'est pas renseignée.
     *
     * @param  array{spoken: string, subtitle: string}  $question
     * @return list<array{spoken: string, subtitle: string, kind: string, closing?: bool}>|null
     */
    public static function segments(array $question, ?string $site = null): ?array
    {
        $site ??= VideoSettings::site();

        if ($site === null) {
            return null;
        }

        $subscribe = (string) config('videos.ending.subscribe');

        return [
            ['spoken' => $question['spoken'], 'subtitle' => $question['subtitle'], 'kind' => self::QUESTION],
            ['spoken' => $subscribe, 'subtitle' => $subscribe, 'kind' => self::SUBSCRIBE],
            ['spoken' => self::closing($site, spoken: true), 'subtitle' => self::closing($site), 'kind' => self::CLOSING, 'closing' => true],
        ];
    }

    /** Phrase de l'action principale, affichée (adresse telle quelle) ou dite (« talkami point net »). */
    public static function closing(string $site, bool $spoken = false): string
    {
        return str_replace(':site', $spoken ? str_replace('.', ' point ', $site) : $site, (string) config('videos.closing')).'.';
    }

    /** Une partie de la fin (question, abonnement, action principale) ? Les anciens scripts n'avaient que « closing ». */
    public static function isEnding(array $segment): bool
    {
        return in_array($segment['kind'] ?? null, [self::QUESTION, self::SUBSCRIBE, self::CLOSING], true) || ($segment['closing'] ?? false);
    }

    /** Parties du corps de la leçon (sans la fin). */
    public static function body(array $script): array
    {
        return array_values(array_filter($script['segments'] ?? [], fn (array $segment) => ! self::isEnding($segment)));
    }

    /** La fin du script est-elle celle d'aujourd'hui (question présente, même phrase d'abonnement, même adresse) ? */
    public static function isCurrent(array $script): bool
    {
        if (! isset($script['question']['spoken'], $script['question']['subtitle'])) {
            return false;
        }

        $expected = self::segments($script['question']);
        $actual = array_values(array_filter($script['segments'] ?? [], fn (array $segment) => self::isEnding($segment)));

        return $expected !== null && array_map(fn ($s) => [$s['spoken'], $s['subtitle']], $expected) === array_map(fn ($s) => [$s['spoken'] ?? null, $s['subtitle'] ?? null], $actual);
    }
}
