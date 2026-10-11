<?php

namespace App\Support;

use App\Models\Attestation;

/**
 * Attestation de niveau au format PDF, écrite sans bibliothèque externe :
 * une page A4, polices standard Helvetica (accents en WinAnsiEncoding).
 */
class AttestationPdf
{
    private const WIDTH = 595;

    private const HEIGHT = 842;

    /**
     * @var list<string>
     */
    private array $commands = [];

    public static function render(Attestation $attestation): string
    {
        return (new self)->compose($attestation);
    }

    private function compose(Attestation $attestation): string
    {
        $user = $attestation->user;
        $level = $attestation->level;
        $contact = config('platform.contact');

        // Cadre.
        $this->commands[] = '0.05 0.42 0.31 RG 3 w 30 30 535 782 re S 0.7 w 38 38 519 766 re S';

        $y = 740;
        $y = $this->centered(mb_strtoupper(config('platform.name')), 14, $y, bold: true);
        $y = $this->centered('Apprendre l\'anglais à l\'oral', 10, $y - 4);
        $y = $this->centered('ATTESTATION DE NIVEAU EN ANGLAIS', 22, $y - 50, bold: true);
        $y = $this->centered('Nous attestons que', 12, $y - 30);
        $y = $this->centered($user->name, 20, $y - 14, bold: true);
        $y = $this->centered('a validé le niveau', 12, $y - 14);
        $y = $this->centered($level->value.' — '.$level->label(), 26, $y - 16, bold: true);

        $text = sprintf(
            'du Cadre européen commun de référence pour les langues (CECRL), en anglais, après avoir suivi les 12 unités '
            .'du programme %s de %s et réussi le bilan oral de fin de niveau le %s.',
            $level->value,
            config('platform.name'),
            $attestation->issued_at->translatedFormat('j F Y'),
        );

        $y -= 16;

        foreach (explode("\n", wordwrap($text, 78)) as $line) {
            $y = $this->centered($line, 11, $y);
        }

        $y = $this->centered('Délivrée le '.$attestation->issued_at->translatedFormat('j F Y').'.', 11, $y - 30);

        $this->text('Code de vérification : '.$attestation->code, 10, 70, 150, bold: true);
        $this->text('Vérifier cette attestation : '.route('attestation.verify', $attestation->code), 9, 70, 134);
        $this->text($contact['address'].' — '.implode(' / ', $contact['phones']), 8, 70, 104);
        $this->text($contact['email'], 8, 70, 92);

        return $this->document(implode("\n", $this->commands));
    }

    /**
     * Ligne centrée (largeur estimée : les polices standard n'ont pas besoin d'être intégrées).
     */
    private function centered(string $text, int $size, int $y, bool $bold = false): int
    {
        $width = mb_strlen($text) * $size * ($bold ? 0.56 : 0.5);
        $this->text($text, $size, (int) max(45, (self::WIDTH - $width) / 2), $y, $bold);

        return $y - (int) round($size * 1.4);
    }

    private function text(string $text, int $size, int $x, int $y, bool $bold = false): void
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);

        $this->commands[] = sprintf('BT 0.11 0.15 0.13 rg /%s %d Tf %d %d Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $x, $y, $escaped);
    }

    private function document(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>', self::WIDTH, self::HEIGHT),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
