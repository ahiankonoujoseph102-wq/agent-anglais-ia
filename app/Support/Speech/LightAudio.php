<?php

namespace App\Support\Speech;

use App\Support\Videos\VideoVoice;

/**
 * Version légère d'un message vocal, pour le préchargement de la page de bienvenue : le même son en µ-law 8 bits
 * (codage du téléphone, WAV format 7), deux fois plus petit que le PCM 16 bits, même fréquence (toute la bande de la
 * voix est gardée). Le navigateur qui ne sait pas le lire redemande la version complète.
 */
final class LightAudio
{
    /** Table PCM 16 bits (0 à 65535, petit-boutiste non signé) → octet µ-law, calculée une fois. */
    private static ?array $table = null;

    /**
     * WAV µ-law d'un WAV PCM 16 bits, ou null si le fichier n'est pas lisible.
     */
    public static function mulaw(string $wav): ?string
    {
        $parsed = VideoVoice::parse($wav);

        if ($parsed === null) {
            return null;
        }

        $table = self::table();
        $bytes = '';

        foreach (str_split($parsed['data'], 131072) as $chunk) {
            $bytes .= pack('C*', ...array_map(fn (int $value) => $table[$value], array_values(unpack('v*', $chunk) ?: [])));
        }

        $rate = $parsed['rate'];
        $channels = $parsed['channels'];
        // En-tête WAV de 46 octets (fmt de 18 octets, obligatoire hors PCM), sans bloc « fact » (lu sans lui).
        $fmt = pack('vvVVvvv', 7, $channels, $rate, $rate * $channels, $channels, 8, 0);

        return 'RIFF'.pack('V', 4 + 8 + strlen($fmt) + 8 + strlen($bytes)).'WAVE'
            .'fmt '.pack('V', strlen($fmt)).$fmt
            .'data'.pack('V', strlen($bytes)).$bytes.(strlen($bytes) % 2 ? "\0" : '');
    }

    /**
     * @return array<int, int>
     */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];

        for ($value = 0; $value < 65536; $value++) {
            $table[$value] = self::encode($value >= 32768 ? $value - 65536 : $value);
        }

        return self::$table = $table;
    }

    /** Codage µ-law G.711 d'un échantillon 16 bits signé. */
    public static function encode(int $sample): int
    {
        $sign = $sample < 0 ? 0x80 : 0;
        $magnitude = min(32635, abs($sample)) + 0x84;
        $exponent = 7;

        for ($mask = 0x4000; ($magnitude & $mask) === 0 && $exponent > 0; $mask >>= 1) {
            $exponent--;
        }

        $mantissa = ($magnitude >> ($exponent + 3)) & 0x0F;

        return ~($sign | ($exponent << 4) | $mantissa) & 0xFF;
    }
}
