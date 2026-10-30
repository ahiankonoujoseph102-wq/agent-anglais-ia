<?php

namespace App\Support\Speech;

use App\Support\Videos\VideoVoice;

/**
 * Volume des messages vocaux préparés (bienvenue, résultat de l'évaluation) : sonie intégrée amenée à
 * « platform.speech.target_lufs » (−14 LUFS, comme les vidéos), mesurée selon la recommandation UIT-R BS.1770
 * (filtre de pondération K, blocs de 400 millisecondes, seuils à −70 LUFS puis à −10 unités sous la moyenne).
 * Les crêtes sont tenues sous « platform.speech.peak_db » (−1 décibel) par un limiteur doux (attaque de quelques
 * millisecondes, relâchement de 60 millisecondes) : la voix est forte sans jamais saturer.
 *
 * Seulement du WAV PCM 16 bits mono (la synthèse vocale) ; tout autre fichier passe par Loudness::normalize().
 */
class SpeechLoudness
{
    /** Écart toléré avant de retoucher un fichier, en unités de sonie. */
    private const TOLERANCE = 0.5;

    public static function targetLufs(): float
    {
        return (float) config('platform.speech.target_lufs', -14.0);
    }

    /**
     * Sonie intégrée d'un WAV PCM 16 bits mono, en LUFS ; null si illisible ou silencieux.
     */
    public static function lufs(string $wav): ?float
    {
        $parsed = VideoVoice::parse($wav);

        if ($parsed === null || $parsed['channels'] !== 1) {
            return null;
        }

        return self::integrated(self::samples($parsed['data']), $parsed['rate']);
    }

    /**
     * Amène le message à la sonie visée, crêtes limitées ; rendu tel quel s'il y est déjà.
     */
    public static function normalize(string $wav, ?float $target = null): string
    {
        $parsed = VideoVoice::parse($wav);

        if ($parsed === null || $parsed['channels'] !== 1) {
            return Loudness::normalize($wav);
        }

        $target ??= self::targetLufs();
        $samples = self::samples($parsed['data']);
        $rate = $parsed['rate'];

        // Moins de 400 millisecondes : pas de sonie mesurable, seulement la crête.
        if (count($samples) < $rate * 0.4) {
            return Loudness::normalize($wav);
        }

        $loudness = self::integrated($samples, $rate);

        if ($loudness === null) {
            return $wav;
        }

        $ceiling = 10 ** (Loudness::targetDb() / 20);
        $peak = max(array_map('abs', [max($samples), min($samples)]));

        if (abs($loudness - $target) <= self::TOLERANCE && $peak <= $ceiling + 1e-4) {
            return $wav;
        }

        // Deux passes : le limiteur retire un peu de sonie, la seconde passe la rend.
        $gain = 10 ** (($target - $loudness) / 20);

        for ($pass = 0; $pass < 2; $pass++) {
            $out = self::limit(array_map(fn (float $value) => $value * $gain, $samples), $rate, $ceiling);
            $reached = self::integrated($out, $rate);

            if ($reached === null || abs($reached - $target) <= self::TOLERANCE / 2) {
                break;
            }

            $gain *= 10 ** (($target - $reached) / 20);
        }

        return VideoVoice::wav(pack('v*', ...array_map(fn (float $value) => ((int) round($value * 32767)) & 0xFFFF, $out)), $rate, 1);
    }

    /**
     * @return list<float> échantillons entre −1 et 1
     */
    private static function samples(string $data): array
    {
        return array_map(fn (int $value) => ($value >= 32768 ? $value - 65536 : $value) / 32768, array_values(unpack('v*', $data) ?: []));
    }

    /**
     * Sonie intégrée (BS.1770-4) d'échantillons mono.
     *
     * @param  list<float>  $samples
     */
    public static function integrated(array $samples, int $rate): ?float
    {
        $weighted = self::kWeight($samples, $rate);
        $count = count($weighted);
        $block = (int) round($rate * 0.4);
        $step = (int) round($rate * 0.1);

        if ($count < $block) {
            return null;
        }

        // Somme des carrés cumulée : chaque bloc en une soustraction.
        $cumulative = [0.0];
        $sum = 0.0;

        foreach ($weighted as $value) {
            $sum += $value * $value;
            $cumulative[] = $sum;
        }

        $powers = [];

        for ($start = 0; $start + $block <= $count; $start += $step) {
            $power = ($cumulative[$start + $block] - $cumulative[$start]) / max(1, $block);

            if ($power > 0 && -0.691 + 10 * log10($power) > -70) {
                $powers[] = $power;
            }
        }

        if ($powers === []) {
            return null;
        }

        $relative = -0.691 + 10 * log10(array_sum($powers) / count($powers)) - 10;
        $kept = array_filter($powers, fn (float $power) => -0.691 + 10 * log10($power) > $relative);

        return $kept === [] ? null : -0.691 + 10 * log10(array_sum($kept) / count($kept));
    }

    /**
     * Pondération K : étage « tête » (plateau haut de +4 décibels) puis passe-haut à 38 hertz, coefficients
     * calculés pour la fréquence d'échantillonnage (mêmes formules que pyloudnorm).
     *
     * @param  list<float>  $samples
     * @return list<float>
     */
    private static function kWeight(array $samples, int $rate): array
    {
        $a = 10 ** (3.999843853973347 / 40);
        $w = 2 * M_PI * 1681.974450955533 / $rate;
        $alpha = sin($w) / (2 * 0.7071752369554196);
        $cos = cos($w);
        $root = 2 * sqrt($a) * $alpha;
        $shelf = [
            $a * (($a + 1) + ($a - 1) * $cos + $root), -2 * $a * (($a - 1) + ($a + 1) * $cos), $a * (($a + 1) + ($a - 1) * $cos - $root),
            ($a + 1) - ($a - 1) * $cos + $root, 2 * (($a - 1) - ($a + 1) * $cos), ($a + 1) - ($a - 1) * $cos - $root,
        ];

        $w = 2 * M_PI * 38.13547087602444 / $rate;
        $alpha = sin($w) / (2 * 0.5003270373238773);
        $cos = cos($w);
        $highPass = [(1 + $cos) / 2, -(1 + $cos), (1 + $cos) / 2, 1 + $alpha, -2 * $cos, 1 - $alpha];

        return self::biquad(self::biquad($samples, $shelf), $highPass);
    }

    /**
     * @param  list<float>  $samples
     * @param  array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}  $c  b0, b1, b2, a0, a1, a2
     * @return list<float>
     */
    private static function biquad(array $samples, array $c): array
    {
        [$b0, $b1, $b2, $a0, $a1, $a2] = $c;
        [$b0, $b1, $b2, $a1, $a2] = [$b0 / $a0, $b1 / $a0, $b2 / $a0, $a1 / $a0, $a2 / $a0];
        $x1 = $x2 = $y1 = $y2 = 0.0;
        $out = [];

        foreach ($samples as $x) {
            $y = $b0 * $x + $b1 * $x1 + $b2 * $x2 - $a1 * $y1 - $a2 * $y2;
            $x2 = $x1;
            $x1 = $x;
            $y2 = $y1;
            $y1 = $y;
            $out[] = $y;
        }

        return $out;
    }

    /**
     * Limiteur : gain de chaque échantillon jamais au-dessus de « plafond / niveau », approché en douceur avant une
     * crête (passe arrière, quelques millisecondes) et relâché après (passe avant, 60 millisecondes).
     *
     * @param  list<float>  $samples
     * @return list<float>
     */
    private static function limit(array $samples, int $rate, float $ceiling): array
    {
        $count = count($samples);
        $attack = 1 - exp(-1 / ($rate * 0.003));
        $release = 1 - exp(-1 / ($rate * 0.06));
        $gains = array_map(fn (float $value) => abs($value) > $ceiling ? $ceiling / abs($value) : 1.0, $samples);

        for ($i = $count - 2; $i >= 0; $i--) {
            $gains[$i] = min($gains[$i], $gains[$i + 1] + (1 - $gains[$i + 1]) * $attack);
        }

        for ($i = 1; $i < $count; $i++) {
            $gains[$i] = min($gains[$i], $gains[$i - 1] + (1 - $gains[$i - 1]) * $release);
        }

        for ($i = 0; $i < $count; $i++) {
            $samples[$i] = max(-1.0, min(1.0, $samples[$i] * $gains[$i]));
        }

        return $samples;
    }
}
