<?php

namespace App\Console\Commands;

use App\Support\LearnerInvitations;
use App\Support\LearnerReset;
use Illuminate\Console\Command;

/**
 * Met en file les invitations à refaire l'évaluation gratuite pour les comptes d'une sauvegarde de
 * app:reset-learners, SANS rien effacer (par exemple si la remise à zéro a été lancée sans --notify, ou coupée).
 * Sans --force : simulation. Jamais une invitation déjà envoyée ni déjà en file, jamais un apprenant qui a déjà
 * refait son évaluation. Envoi ensuite par learners:send-invitations (chaque heure).
 */
class QueueLearnerInvitations extends Command
{
    protected $signature = 'learners:queue-invitations
        {file : Sauvegarde de la remise à zéro (backups/remise-a-zero-….json.gz)}
        {--force : Mettre en file pour de vrai (sinon : simulation)}';

    protected $description = 'Met en file les invitations d\'une remise à zéro, sans rien effacer ; simulation sans --force';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        try {
            $data = LearnerReset::readBackup($file);
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage().' Rien n\'a été mis en file.');

            return self::FAILURE;
        }

        $plan = LearnerInvitations::plan(array_map(fn (array $user) => (int) $user['id'], $data['users']));

        $this->line("Comptes d'apprenants de la sauvegarde encore inscrits : {$plan['accounts']}.");
        $this->line("Déjà envoyées (jamais en double) : {$plan['already_sent']} ; déjà en file ou notées sans adresse : {$plan['already_queued']} ; évaluation déjà refaite : {$plan['evaluated']}.");
        $this->line('À mettre en file : '.count($plan['to_queue'])." ({$plan['with_email']} avec une adresse e-mail, {$plan['no_email']} sans adresse : rien ne leur sera envoyé).");

        if (! $this->option('force')) {
            $this->info('Simulation : rien n\'a été mis en file.');

            if ($plan['to_queue'] !== []) {
                $this->line("Pour les mettre en file : php artisan learners:queue-invitations {$file} --force");
            }

            return self::SUCCESS;
        }

        $queued = LearnerInvitations::queue($plan['to_queue']);
        $this->info("Invitations : {$queued['queued']} en file d'attente, {$queued['no_email']} sans adresse e-mail. Rien n'a été effacé.");
        $this->line('Envoi chaque heure (learners:send-invitations), dans la limite d\'e-mails du jour. Suivi : administration, « Alertes ».');

        return self::SUCCESS;
    }
}
