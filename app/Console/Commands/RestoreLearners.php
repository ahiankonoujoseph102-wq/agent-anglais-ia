<?php

namespace App\Console\Commands;

use App\Support\LearnerReset;
use Illuminate\Console\Command;

/**
 * Remet en place une sauvegarde faite par app:reset-learners (lignes, niveaux, fichiers). Rien n'est écrasé :
 * si une ligne existe déjà, la restauration est refusée en entier.
 */
class RestoreLearners extends Command
{
    protected $signature = 'app:restore-learners
        {file : Chemin de la sauvegarde sur le disque privé (backups/remise-a-zero-….json.gz)}';

    protected $description = 'Remet en place une sauvegarde de app:reset-learners';

    public function handle(): int
    {
        try {
            $result = LearnerReset::restore((string) $this->argument('file'));
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sauvegarde remise en place : '.array_sum($result['rows']).' ligne(s) et '.$result['files'].' fichier(s).');

        foreach ($result['rows'] as $table => $count) {
            $this->line("  {$table} : {$count}");
        }

        return self::SUCCESS;
    }
}
