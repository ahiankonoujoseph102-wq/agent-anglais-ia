<?php

namespace App\Console\Commands;

use App\Support\LearnerInvitations;
use Illuminate\Console\Command;

/**
 * Envoie les invitations à refaire l'évaluation gratuite (après app:reset-learners --notify), dans la marge
 * d'e-mails du jour. Lancée chaque heure par le planificateur ; ne fait jamais échouer une tâche.
 */
class SendLearnerInvitations extends Command
{
    protected $signature = 'learners:send-invitations';

    protected $description = 'Envoie les invitations à refaire l\'évaluation gratuite, dans la limite d\'e-mails du jour';

    public function handle(): int
    {
        $result = LearnerInvitations::sendDue();

        if (! $result['configured']) {
            $this->warn("Envoi d'e-mails non réglé (MAIL_*) : {$result['remaining']} invitation(s) en attente.");

            return self::SUCCESS;
        }

        $this->info("Invitations envoyées : {$result['sent']}, en échec : {$result['failed']}, encore en attente : {$result['remaining']}.");

        if ($result['remaining'] > 0) {
            $this->line('La limite d\'e-mails du jour est atteinte : la suite partira les jours suivants, chaque heure.');
        }

        return self::SUCCESS;
    }
}
