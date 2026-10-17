<?php

namespace App\Support;

/**
 * Nombre de pages d'un PDF, sans bibliothèque : lecture de la structure du fichier.
 *
 * Les PDF récents rangent leurs objets dans des flux compressés (« object streams ») : ils sont
 * décompressés (avec une borne de taille) avant la recherche. Le nombre de pages est celui de
 * l'arbre racine (/Type /Pages sans /Parent, valeur /Count) ; à défaut, le nombre d'objets
 * /Type /Page. Renvoie null si le fichier n'est pas un PDF ou si le nombre reste introuvable
 * (PDF chiffré ou abîmé) : l'appelant le refuse alors poliment.
 */
class PdfPages
{
    /** Octets décompressés au plus, tous flux confondus (protection contre les fichiers piégés). */
    private const MAX_INFLATED = 24 * 1024 * 1024;

    public static function count(string $bytes): ?int
    {
        if (! str_starts_with(ltrim(substr($bytes, 0, 1024)), '%PDF-')) {
            return null;
        }

        $text = $bytes."\n".self::inflatedStreams($bytes);

        // Racine de l'arbre des pages : le dictionnaire /Type /Pages qui n'a pas de /Parent.
        $roots = [];

        if (preg_match_all('/\/Type\s*\/Pages(?![A-Za-z])/', $text, $found, PREG_OFFSET_CAPTURE)) {
            foreach ($found[0] as [, $position]) {
                $dictionary = self::dictionaryAround($text, $position);

                if ($dictionary !== null && ! str_contains($dictionary, '/Parent') && preg_match('/\/Count\s+(\d+)/', $dictionary, $count)) {
                    $roots[] = (int) $count[1];
                }
            }
        }

        if ($roots !== []) {
            return max($roots) ?: null;
        }

        $pages = preg_match_all('/\/Type\s*\/Page(?![A-Za-z])/', $text);

        return $pages > 0 ? $pages : null;
    }

    /**
     * Dictionnaire « << … >> » qui contient la position donnée (imbrications comprises), ou null.
     */
    private static function dictionaryAround(string $text, int $position): ?string
    {
        $depth = 0;
        $start = null;

        for ($i = $position, $limit = max(0, $position - 4000); $i > $limit; $i--) {
            $pair = substr($text, $i - 1, 2);

            if ($pair === '>>') {
                $depth++;
                $i--;
            } elseif ($pair === '<<') {
                if ($depth === 0) {
                    $start = $i - 1;
                    break;
                }

                $depth--;
                $i--;
            }
        }

        if ($start === null) {
            return null;
        }

        $depth = 0;

        for ($i = $start, $max = min(strlen($text) - 1, $start + 12000); $i < $max; $i++) {
            $pair = substr($text, $i, 2);

            if ($pair === '<<') {
                $depth++;
                $i++;
            } elseif ($pair === '>>') {
                $depth--;
                $i++;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Contenu décompressé des flux (Flate) du fichier, jusqu'à la borne.
     */
    private static function inflatedStreams(string $bytes): string
    {
        $out = '';
        $budget = self::MAX_INFLATED;
        $offset = 0;

        while ($budget > 0 && preg_match('/stream\r?\n/', $bytes, $start, PREG_OFFSET_CAPTURE, $offset)) {
            $from = $start[0][1] + strlen($start[0][0]);
            $end = strpos($bytes, 'endstream', $from);

            if ($end === false) {
                break;
            }

            $raw = substr($bytes, $from, $end - $from);
            $offset = $end + 9;
            $data = @zlib_decode($raw, $budget);

            if (is_string($data) && $data !== '') {
                // Un flux de contenu (dessin) ne contient jamais de dictionnaire de page : on ne garde que le texte lisible.
                if (str_contains($data, '/Type') || str_contains($data, '/Count')) {
                    $out .= $data."\n";
                }

                $budget -= strlen($data);
            }
        }

        return $out;
    }
}
