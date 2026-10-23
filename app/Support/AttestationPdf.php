<?php

namespace App\Support;

use App\Models\Attestation;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Attestation de niveau au format PDF, aux couleurs de la plateforme (vue pdf/attestation), produite par dompdf comme
 * les documents de révision : police Sora et logo pris dans resources/pdf (jamais d'adresse distante), une page A4.
 */
class AttestationPdf
{
    public static function render(Attestation $attestation): string
    {
        $directory = storage_path('framework/dompdf');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $options = new Options;
        $options->setChroot([resource_path('pdf')]);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setFontDir($directory);
        $options->setFontCache($directory);
        $options->setTempDir($directory);
        $options->setDefaultFont('Sora');
        $options->setDefaultPaperSize('a4');

        $pdf = new Dompdf($options);
        $pdf->loadHtml(self::html($attestation), 'UTF-8');
        $pdf->render();
        $pdf->addInfo('Title', 'Attestation de niveau '.$attestation->level->value.' · '.config('platform.name'));

        return (string) $pdf->output();
    }

    /**
     * HTML de l'attestation (vue pdf/attestation), avec la typographie française du site.
     */
    public static function html(Attestation $attestation): string
    {
        $attestation->loadMissing('user');
        $level = $attestation->level;
        $contact = SiteSettings::contact();
        $platform = (string) config('platform.name');
        $date = $attestation->issued_at->timezone(config('app.timezone'))->locale('fr')->translatedFormat('j F Y');

        $html = view('pdf.attestation', [
            'title' => 'Attestation de niveau · '.$platform,
            'platform' => $platform,
            'tagline' => (string) config('platform.brand.tagline'),
            'learner' => (string) $attestation->user?->name,
            'level' => $level->value,
            'levelLabel' => $level->label(),
            'text' => sprintf(
                'du Cadre européen commun de référence pour les langues (CECRL), en anglais, après avoir suivi les 12 unités '
                .'du programme %s de %s et réussi le bilan oral de fin de niveau le %s.',
                $level->value, $platform, $date,
            ),
            'date' => $date,
            'code' => $attestation->code,
            'verifyUrl' => route('attestation.verify', $attestation->code),
            'contactLine' => implode(' — ', array_filter([$contact['address'], implode(' / ', $contact['phones'])])),
            'email' => $contact['email'],
            'fonts' => ['regular' => resource_path('pdf/sora-regular.ttf'), 'bold' => resource_path('pdf/sora-bold.ttf')],
            // Sans l'extension GD, dompdf ne sait pas lire le PNG : l'attestation sort alors avec le nom en texte.
            'logo' => extension_loaded('gd') ? resource_path('pdf/logo-blanc.png') : '',
        ])->render();

        return Typography::html($html);
    }
}
