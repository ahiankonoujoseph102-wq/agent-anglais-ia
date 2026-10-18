<?php

namespace App\Console\Commands;

use App\Support\Gemini\SpeechPreparer;
use Illuminate\Console\Command;

/**
 * Prépare à l'avance les messages vocaux communs (message de bienvenue, messages du résultat de l'évaluation),
 * pour qu'ils soient prêts avant la première écoute. Lancée à chaque déploiement (après la migration) et
 * chaque heure par le planificateur ; ne fait aucune demande quand tout est prêt.
 * Ne renvoie JAMAIS d'échec : un problème chez Google ne doit pas faire échouer le déploiement.
 */
class PrepareVoice extends Command
{
    protected $signature = 'app:prepare-voice {--budget= : temps accordé, en secondes}';

    protected $description = 'Prépare les messages vocaux communs (bienvenue, résultat de l\'évaluation) sans jamais faire échouer le déploiement';

    public function handle(SpeechPreparer $preparer): int
    {
        $budget = (int) ($this->option('budget') ?: config('services.gemini.tts_prepare_budget'));

        try {
            $result = $preparer->run($budget, fn (string $line) => $this->line($line));
            $this->info(SpeechPreparer::summary($result));
        } catch (\Throwable $e) {
            report($e);
            $this->warn('Préparation des messages vocaux interrompue ('.class_basename($e).'). Le site fonctionne : les messages manquants restent affichés en texte.');
        }

        return self::SUCCESS;
    }
}
