<?php

namespace App\Http\Requests\Course;

use App\Support\Duration;
use App\Support\PdfPages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Envoi d'une leçon « Mes cours » : de 1 à 4 photos, OU un PDF (20 mégaoctets et 50 pages au plus).
 * Les limites viennent de config/platform.php (courses).
 */
class StoreCourseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'pages' => ['nullable', 'array', 'max:'.(int) config('platform.courses.max_photos')],
            'pages.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('platform.courses.photo_max_kilobytes')],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.(int) config('platform.courses.pdf_max_kilobytes')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $photos = $this->photos();
            $pdf = $this->pdf();

            if ($photos === [] && $pdf === null) {
                $validator->errors()->add('pages', 'Ajoutez une photo de votre leçon ou un PDF.');

                return;
            }

            if ($photos !== [] && $pdf !== null) {
                $validator->errors()->add('pages', 'Envoyez soit des photos, soit un PDF, pas les deux en même temps.');

                return;
            }

            $total = array_sum(array_map(fn (UploadedFile $file) => (int) $file->getSize(), $photos));

            if ($total > (int) config('platform.courses.photos_total_max_kilobytes') * 1024) {
                $validator->errors()->add('pages', 'Ces photos sont trop lourdes ensemble. Envoyez-en moins, ou reprenez-les avec le même appareil photo : elles sont réduites avant l\'envoi.');

                return;
            }

            if ($pdf !== null) {
                $pages = PdfPages::count((string) $pdf->get());
                $max = (int) config('platform.courses.pdf_max_pages');

                if ($pages === null) {
                    $validator->errors()->add('pdf', 'Ce PDF ne peut pas être ouvert (protégé ou abîmé). Envoyez plutôt des photos de vos pages.');
                } elseif ($pages > $max) {
                    $validator->errors()->add('pdf', "Votre PDF a {$pages} pages : {$max} pages au plus. Envoyez seulement les pages utiles.");
                }
            }
        }];
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('pages'), fn ($file) => $file instanceof UploadedFile));
    }

    public function pdf(): ?UploadedFile
    {
        $pdf = $this->file('pdf');

        return $pdf instanceof UploadedFile ? $pdf : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $photoMegabytes = (int) (config('platform.courses.photo_max_kilobytes') / 1024);
        $pdfMegabytes = (int) (config('platform.courses.pdf_max_kilobytes') / 1024);

        return [
            'title.max' => 'Le titre est trop long : 150 caractères au plus.',
            'pages.max' => 'Quatre pages au plus par leçon. Retirez-en une ou envoyez la suite comme une autre leçon.',
            'pages.*.image' => 'Chaque page doit être une photo (JPEG, PNG ou WebP).',
            'pages.*.mimes' => 'Chaque page doit être une photo (JPEG, PNG ou WebP).',
            'pages.*.max' => "Une photo est trop lourde : {$photoMegabytes} mégaoctets au plus.",
            'pages.*.uploaded' => 'Une photo n\'a pas pu être envoyée : elle est peut-être trop lourde.',
            'pdf.mimes' => 'Le fichier doit être un PDF.',
            'pdf.mimetypes' => 'Le fichier doit être un PDF.',
            'pdf.max' => "Votre fichier dépasse {$pdfMegabytes} mégaoctets : envoyez seulement les pages utiles.",
            'pdf.uploaded' => 'Votre fichier est trop lourd pour être envoyé : envoyez seulement les pages utiles.',
        ];
    }

    /**
     * Phrase d'attente affichée quand la lecture est trop fréquente.
     */
    public static function waitMessage(int $seconds): string
    {
        return 'Vous avez envoyé beaucoup de leçons. Réessayez dans '.Duration::format($seconds + 59).'.';
    }
}
