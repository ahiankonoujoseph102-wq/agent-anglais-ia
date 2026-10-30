<?php

namespace App\Console\Commands;

use App\Support\Videos\VideoMailer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Envoie au fondateur, par e-mail, les vidéos de promotion et du Club prêtes, puis les retire du stockage (après la
 * durée de garde réglée dans la page « Vidéos »). Chaque minute par le planificateur. Ne fait jamais échouer une tâche.
 */
class MailVideos extends Command
{
    protected $signature = 'videos:mail';

    protected $description = 'Envoie les vidéos prêtes par e-mail au fondateur, puis les retire du stockage';

    public function handle(VideoMailer $mailer): int
    {
        try {
            $result = $mailer->run();
            $this->line("Vidéos envoyées : {$result['sent']}, en échec : {$result['failed']}, retirées du stockage : {$result['purged']}, en attente d'envoi : {$result['waiting']}.");
        } catch (Throwable $e) {
            $this->line('Envoi des vidéos interrompu ('.class_basename($e).').');
        }

        return self::SUCCESS;
    }
}
