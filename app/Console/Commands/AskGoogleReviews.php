<?php

namespace App\Console\Commands;

use App\Models\LearnerPushSubscription;
use App\Support\Reviews\GoogleReviews;
use Illuminate\Console\Command;

/**
 * Demande d'avis sur Google à tous les élèves inscrits (une fois par compte). Sans --force : simulation.
 */
class AskGoogleReviews extends Command
{
    protected $signature = 'learners:google-review {--force : Préparer les e-mails et envoyer les notifications}';

    protected $description = 'Demande à chaque élève inscrit, une fois, de laisser un avis sur la page Google (e-mail et notification)';

    public function handle(): int
    {
        $targets = GoogleReviews::targets()->count();
        $withEmail = GoogleReviews::targets()->whereNotNull('email')->where('email', '!=', '')->count();
        $withPhone = GoogleReviews::targets()->whereIn('id', LearnerPushSubscription::query()->select('user_id'))->count();

        $this->line("Élèves qui n'ont pas encore reçu la demande : {$targets}");
        $this->line("Avec une adresse e-mail (recevront l'e-mail, par lots) : {$withEmail}");
        $this->line("Avec les notifications activées sur leur téléphone : {$withPhone}");

        if (! $this->option('force')) {
            $this->warn('Simulation : rien n\'a été envoyé. Pour lancer : php artisan learners:google-review --force');

            return self::SUCCESS;
        }

        $result = GoogleReviews::launch();

        $this->info("C'est lancé : {$result['queued']} demande(s) préparée(s), {$result['push_sent']} notification(s) envoyée(s). Les e-mails partent par lots chaque heure, entre 6 heures et 20 heures.");

        return self::SUCCESS;
    }
}
