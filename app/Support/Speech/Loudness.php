<?php

namespace App\Support\Speech;

/**
 * Volume des messages vocaux préparés (bienvenue, résultat de l'évaluation) : l'audio WAV PCM 16 bits est amené à
 * une crête de « platform.speech.peak_db » (−1 décibel), pour qu'un message ne soit jamais plus bas qu'un autre. Un
 * fichier qu'on ne sait pas lire est rendu tel quel.
 */
class Loudness
{
    /** Écart toléré avant de retoucher un fichier (déjà à la bonne crête, à 0,2 décibel près). */
    private const TOLERANCE_DB = 0.2;

    /** Échantillons traités à la fois (mémoire limitée, même pour un long message). */
    private const CHUNK = 65536;

    public static function targetDb(): float
    {
        return (float) config('platform.speech.peak_db', -1.0);
    }

    /**
     * Crête de l'audio en décibels (0 = plein volume), ou null si ce n'est pas du WAV PCM 16 bits lisible.
     */
    public static function peakDb(string $wav): ?float
    {
        $data = self::dataChunk($wav);

        if ($data === null) {
            return null;
        }

        $peak = self::peak($wav, $data[0], $data[1]);

        return $peak === 0 ? null : 20 * log10($peak / 32768);
    }

    public static function normalize(string $wav, ?float $targetDb = null): string
    {
        $data = self::dataChunk($wav);

        if ($data === null) {
            return $wav;
        }

        [$offset, $length] = $data;
        $peak = self::peak($wav, $offset, $length);

        if ($peak === 0) {
            return $wav;
        }

        $target = $targetDb ?? self::targetDb();

        if (abs(20 * log10($peak / 32768) - $target) <= self::TOLERANCE_DB) {
            return $wav;
        }

        $factor = (32768 * 10 ** ($target / 20)) / $peak;
        $out = substr($wav, 0, $offset);

        for ($start = 0; $start < $length; $start += self::CHUNK * 2) {
            $bytes = substr($wav, $offset + $start, min(self::CHUNK * 2, $length - $start));
            $samples = array_map(function (int $value) use ($factor) {
                $scaled = (int) round(($value >= 32768 ? $value - 65536 : $value) * $factor);

                return max(-32768, min(32767, $scaled));
            }, array_values(unpack('v*', $bytes) ?: []));
            $out .= pack('v*', ...$samples);
        }

        return $out.substr($wav, $offset + $length);
    }

    /**
     * Position et longueur des échantillons d'un WAV PCM 16 bits, sinon null.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function dataChunk(string $wav): ?array
    {
        if (strlen($wav) < 44 || ! str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            return null;
        }

        $position = 12;
        $pcm16 = false;

        while ($position + 8 <= strlen($wav)) {
            $id = substr($wav, $position, 4);
            $size = unpack('V', substr($wav, $position + 4, 4))[1];

            if ($id === 'fmt ') {
                $format = unpack('vformat/vchannels/Vrate/Vbytes/vblock/vbits', substr($wav, $position + 8, 16));
                $pcm16 = $format['format'] === 1 && $format['bits'] === 16;
            } elseif ($id === 'data') {
                $length = min($size, strlen($wav) - $position - 8);

                return $pcm16 ? [$position + 8, $length - $length % 2] : null;
            }

            $position += 8 + $size + $size % 2;
        }

        return null;
    }

    private static function peak(string $wav, int $offset, int $length): int
    {
        $peak = 0;

        for ($start = 0; $start < $length; $start += self::CHUNK * 2) {
            foreach (unpack('v*', substr($wav, $offset + $start, min(self::CHUNK * 2, $length - $start))) ?: [] as $value) {
                $peak = max($peak, $value >= 32768 ? 65536 - $value : $value);
            }
        }

        return $peak;
    }
}
