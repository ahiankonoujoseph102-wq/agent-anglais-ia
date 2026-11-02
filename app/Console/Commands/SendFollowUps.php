<?php

namespace App\Console\Commands;

use App\Support\Reminders\FollowUps;
use Illuminate\Console\Command;

/** Relances des inscrits sans formule payée (chaque heure) : prépare celles qui sont dues, envoie un petit lot. */
class SendFollowUps extends Command
{
    protected $signature = 'learners:follow-up';

    protected $description = 'Relance par e-mail les inscrits sans formule payée (évaluation, séance d\'essai, fin d\'essai)';

    public function handle(): int
    {
        $result = FollowUps::run();

        $this->info("Relances : {$result['queued']} préparée(s), {$result['sent']} envoyée(s), {$result['no_email']} sans adresse e-mail (à relancer sur WhatsApp), {$result['skipped']} plus d'actualité, {$result['failed']} en échec.");

        return self::SUCCESS;
    }
}
