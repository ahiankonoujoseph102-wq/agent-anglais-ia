<?php

namespace App\Support;

/**
 * Ce que l'élève peut envoyer dans « Mes cours », écrit UNE fois à partir de config/platform.php (courses) :
 * « Ajoutez jusqu'à 4 photos de votre cahier ou de votre livre, ou votre cours en PDF (jusqu'à 50 pages) ».
 * Utilisé partout où « Mes cours » est présenté (accueil, Mon espace, page « Mes cours », foire aux questions).
 */
class CourseFiles
{
    public static function photos(): int
    {
        return (int) config('platform.courses.max_photos');
    }

    public static function pdfPages(): int
    {
        return (int) config('platform.courses.pdf_max_pages');
    }

    public static function partPages(): int
    {
        return (int) config('platform.courses.part_max_pages');
    }

    /**
     * « Ajoutez jusqu'à 4 photos de votre cahier ou de votre livre, ou votre cours en PDF (jusqu'à 50 pages) ».
     */
    public static function offer(): string
    {
        return 'Ajoutez jusqu\'à '.self::photos().' photos de votre cahier ou de votre livre, ou votre cours en PDF (jusqu\'à '.self::pdfPages().' pages)';
    }
}
