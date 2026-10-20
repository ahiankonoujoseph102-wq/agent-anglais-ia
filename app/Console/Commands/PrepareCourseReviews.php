<?php

namespace App\Console\Commands;

use App\Support\CourseReviews;
use Illuminate\Console\Command;

/**
 * Prépare les documents de révision des séances « Mes cours » finies : quelques séances par passage
 * (courses.review.batch), et arrêt dès qu'un document échoue (limite du modèle texte probable) pour ne pas épuiser
 * le quota gratuit d'un coup. Lancée chaque minute par le planificateur, jamais deux fois en même temps
 * (withoutOverlapping, onOneServer). Renvoie toujours un succès.
 */
class PrepareCourseReviews extends Command
{
    protected $signature = 'courses:prepare-reviews {--batch= : nombre de documents au plus à ce passage}';

    protected $description = 'Prépare les documents de révision des séances « Mes cours » finies';

    public function handle(): int
    {
        $queued = CourseReviews::enqueue();
        $batch = max(1, (int) ($this->option('batch') ?: CourseReviews::setting('batch')));
        $ready = 0;
        $failed = 0;

        foreach (CourseReviews::due($batch) as $review) {
            if (CourseReviews::prepare($review)) {
                $ready++;

                continue;
            }

            $failed++;

            break;
        }

        $this->line("Séances ajoutées : {$queued}. Documents prêts : {$ready}. Échecs : {$failed}.");

        return self::SUCCESS;
    }
}
