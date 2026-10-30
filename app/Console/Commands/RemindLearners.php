<?php

namespace App\Console\Commands;

use App\Support\Reminders\LessonReminders;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rappels de la séance du jour aux élèves payants (30 minutes et 1 minute avant) et relance d'une séance manquée
 * (2 heures après). Chaque minute par le planificateur : le serveur est déjà allumé pour les autres tâches, aucun
 * coût en plus. Ne fait jamais échouer une tâche.
 */
class RemindLearners extends Command
{
    protected $signature = 'learners:remind';

    protected $description = 'Envoie les rappels de séance aux élèves payants';

    public function handle(LessonReminders $reminders): int
    {
        try {
            $this->line('Rappels envoyés : '.$reminders->run().'.');
        } catch (Throwable $e) {
            $this->line('Rappels interrompus ('.class_basename($e).').');
        }

        return self::SUCCESS;
    }
}
