<?php

namespace App\Support;

use App\Models\CourseReview;
use App\Support\Teachers\Teachers;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF du document de révision, aux couleurs de Talkami (vue pdf/course-review), produit par dompdf (LGPL, en PHP pur,
 * aucune compilation) : police Sora et logo pris dans resources/pdf (jamais d'adresse distante), en-tête (élève, date,
 * leçon, niveau) et pied de page (site, WhatsApp de l'équipe, « Document personnel de révision, préparé par … »).
 */
class CourseReviewPdf
{
    public static function render(CourseReview $review): string
    {
        $html = self::html($review);
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
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return (string) $pdf->output();
    }

    /**
     * HTML du document (vue pdf/course-review), avec la typographie française du site.
     */
    public static function html(CourseReview $review): string
    {
        $review->loadMissing(['user', 'lesson', 'session']);
        $lesson = $review->lesson;
        $whatsapp = SiteSettings::whatsapp();
        $date = ($review->session?->ended_at ?? $review->prepared_at ?? now())->timezone(config('app.timezone'));

        $html = view('pdf.course-review', [
            'title' => 'Document de révision · '.config('platform.name'),
            'teacher' => ($review->session?->teacher() ?? Teachers::default())->name(),
            'content' => $review->content,
            'learner' => (string) $review->user?->name,
            'date' => $date->locale('fr')->translatedFormat('j F Y'),
            'lessonTitle' => (string) ($lesson?->title ?? 'Ma leçon'),
            'level' => $lesson?->content['level'] ?? $review->user?->level?->value,
            'site' => preg_replace('#^https?://#', '', rtrim(Seo::url(), '/')),
            'whatsapp' => filled($whatsapp) ? PhoneNumber::display(PhoneNumber::normalize($whatsapp) ?? $whatsapp) : null,
            'fonts' => ['regular' => self::file('sora-regular.ttf'), 'bold' => self::file('sora-bold.ttf')],
            // Sans l'extension GD, dompdf ne sait pas lire le PNG : le document sort alors sans logo plutôt que d'échouer.
            'logo' => extension_loaded('gd') ? self::file('logo-blanc.png') : '',
        ])->render();

        // Typographie française du site (espaces insécables devant « ? ! ; : » et dans les guillemets), comme les pages.
        return Typography::html($html);
    }

    /**
     * Adresse locale d'un fichier de resources/pdf (police, logo).
     */
    private static function file(string $name): string
    {
        return resource_path('pdf/'.$name);
    }
}
