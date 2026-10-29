<?php

namespace App\Support\Videos;

/**
 * Voix d'une vidéo demandée en peu de fois : plusieurs parties du script sont dites dans UNE demande de synthèse
 * vocale (la limite gratuite de Gemini est de 10 demandes par jour pour ce modèle), puis le fichier reçu est recoupé
 * en une pièce par partie, au silence le plus proche de l'endroit attendu (d'après la longueur des textes).
 * Les sous-titres, les silences de répétition et l'assemblage restent ceux d'une demande par partie.
 */
class VoiceSplitter
{
    /** Fenêtre d'analyse : 10 millisecondes. */
    private const WINDOW_SECONDS = 0.01;

    /** Silence entre deux phrases : au moins 150 millisecondes calmes. */
    private const MIN_SILENCE_WINDOWS = 15;

    /**
     * Regroupe les parties consécutives en demandes d'au plus « $maxCharacters » caractères.
     *
     * @param  list<string>  $texts
     * @return list<list<int>> index des parties de chaque demande
     */
    public static function groups(array $texts, int $maxCharacters): array
    {
        $groups = [];
        $current = [];
        $length = 0;

        foreach ($texts as $index => $text) {
            $size = mb_strlen($text) + 2;

            if ($current !== [] && $length + $size > $maxCharacters) {
                $groups[] = $current;
                $current = [];
                $length = 0;
            }

            $current[] = $index;
            $length += $size;
        }

        if ($current !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /** Texte d'une demande : les parties séparées par un saut de paragraphe (une vraie pause entre elles). */
    public static function join(array $texts): string
    {
        return implode("\n\n", array_map('trim', $texts));
    }

    /**
     * Recoupe l'audio (PCM 16 bits) d'une demande en autant de pièces que de textes.
     *
     * @param  array{rate: int, channels: int, data: string}  $wav
     * @param  list<string>  $texts
     * @return list<string> données PCM de chaque pièce
     */
    public static function split(array $wav, array $texts): array
    {
        $count = count($texts);

        if ($count <= 1) {
            return [$wav['data']];
        }

        $frame = $wav['channels'] * 2;
        $windowFrames = max(1, (int) round($wav['rate'] * self::WINDOW_SECONDS));
        $levels = self::levels($wav['data'], $frame, $windowFrames);
        $windows = count($levels);
        $quiet = self::quietRuns($levels);

        $weights = array_map(fn (string $text) => max(1, mb_strlen(trim($text))), $texts);
        $total = array_sum($weights);
        $tolerance = max(150, (int) round($windows * 0.12));
        $cuts = [];
        $previous = 0;
        $sum = 0;

        for ($k = 0; $k < $count - 1; $k++) {
            $sum += $weights[$k];
            $expected = (int) round($windows * $sum / $total);
            $cut = self::bestCut($quiet, $expected, $tolerance, $previous + 20, $windows - 20 * ($count - 1 - $k));
            $cuts[] = $cut;
            $previous = $cut;
        }

        $pieces = [];
        $start = 0;

        foreach ([...$cuts, $windows] as $end) {
            $from = $start * $windowFrames * $frame;
            $to = $end >= $windows ? strlen($wav['data']) : $end * $windowFrames * $frame;
            $pieces[] = substr($wav['data'], $from, max(0, $to - $from));
            $start = $end;
        }

        return $pieces;
    }

    /**
     * Niveau (moyenne des valeurs absolues) de chaque fenêtre.
     *
     * @return list<float>
     */
    private static function levels(string $data, int $frame, int $windowFrames): array
    {
        $samples = unpack('v*', $data) ?: [];
        $channels = intdiv($frame, 2);
        $levels = [];
        $sum = 0;
        $n = 0;
        $index = 0;

        foreach ($samples as $value) {
            if ($index++ % $channels !== 0) {
                continue;
            }

            $sum += abs($value >= 32768 ? $value - 65536 : $value);

            if (++$n === $windowFrames) {
                $levels[] = $sum / $n;
                $sum = 0;
                $n = 0;
            }
        }

        if ($n > 0) {
            $levels[] = $sum / $n;
        }

        return $levels;
    }

    /**
     * Passages calmes (au moins 150 millisecondes) : [début, fin) en fenêtres.
     *
     * @param  list<float>  $levels
     * @return list<array{0: int, 1: int}>
     */
    private static function quietRuns(array $levels): array
    {
        if ($levels === []) {
            return [];
        }

        $sorted = $levels;
        sort($sorted);
        $loud = $sorted[(int) floor((count($sorted) - 1) * 0.9)];
        $threshold = max(200.0, $loud * 0.12);
        $runs = [];
        $start = null;

        foreach ([...$levels, PHP_INT_MAX] as $index => $level) {
            if ($level <= $threshold) {
                $start ??= $index;

                continue;
            }

            if ($start !== null && $index - $start >= self::MIN_SILENCE_WINDOWS) {
                $runs[] = [$start, $index];
            }

            $start = null;
        }

        return $runs;
    }

    /**
     * Où couper : à l'endroit attendu s'il tombe dans un silence ; sinon au milieu du silence le plus proche (les
     * plus longs, souvent entre deux parties, passent avant) ; sinon à l'endroit attendu.
     *
     * @param  list<array{0: int, 1: int}>  $quiet
     */
    private static function bestCut(array $quiet, int $expected, int $tolerance, int $min, int $max): int
    {
        $expected = max($min, min($max, $expected));
        $best = null;
        $bestScore = PHP_FLOAT_MAX;

        foreach ($quiet as [$start, $end]) {
            if ($expected >= $start && $expected < $end) {
                return $expected;
            }

            $center = intdiv($start + $end, 2);

            if ($center < $min || $center > $max || abs($center - $expected) > $tolerance) {
                continue;
            }

            $score = abs($center - $expected) - 0.5 * ($end - $start);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $center;
            }
        }

        return $best ?? $expected;
    }
}
