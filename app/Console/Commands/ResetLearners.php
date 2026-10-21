<?php

namespace App\Console\Commands;

use App\Support\LearnerInvitations;
use App\Support\LearnerReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remise à zéro des apprenants (après une grosse amélioration de Teacher Joe) : voir App\Support\LearnerReset.
 *
 * Sans --force : simulation, rien n'est touché. Pour effacer (l'onglet « Commands » de Laravel Cloud n'accepte
 * aucune question, tout passe par des options) :
 *   php artisan app:reset-learners --database=NOM --force --confirm="EFFACER N COMPTES"
 * Sauvegarde automatique avant l'effacement ; retour en arrière : app:restore-learners.
 */
class ResetLearners extends Command
{
    protected $signature = 'app:reset-learners
        {--database= : Nom de la base visée (vérifié contre la base connectée, obligatoire avec --force)}
        {--force : Effacer pour de vrai (sinon : simulation)}
        {--confirm= : Confirmation exacte : « EFFACER N COMPTES » (N = nombre de comptes d\'apprenants)}
        {--include-granted : Retirer aussi les accès offerts en cours (gardés par défaut)}
        {--notify : Inviter par e-mail les apprenants remis à zéro à refaire leur évaluation gratuite}';

    protected $description = 'Remet à zéro les apprenants (évaluation, programme, séances…) ; simulation sans --force';

    public function handle(): int
    {
        $reset = new LearnerReset((bool) $this->option('include-granted'));
        $plan = $reset->plan();
        $connected = (string) DB::connection()->getDatabaseName();
        $expected = LearnerReset::confirmation($plan['accounts']);

        $this->line('Base connectée : '.$connected);
        $this->line("Comptes d'apprenants : {$plan['accounts']} (les administrateurs ne sont jamais touchés).");
        $this->line("Niveaux et messages de bienvenue remis à zéro : {$plan['levels']} compte(s).");
        $this->line('Lignes effacées :');

        foreach ($plan['rows'] as $table => $count) {
            $this->line("  {$table} : {$count}");
        }

        $this->line("Fichiers effacés (Mes cours, documents de révision, attestations) : {$plan['files']}.");
        $this->line('Gardé : comptes, sessions, paiements et captures, données communes, journaux.');
        $this->line($this->option('include-granted')
            ? "Accès offerts en cours retirés : {$plan['granted_removed']}."
            : "Accès offerts en cours gardés : {$plan['granted_kept']} (--include-granted pour les retirer).");
        $this->line("Captures en attente de vérification : {$plan['pending_proofs']}.");

        if ($plan['blockers'] !== []) {
            $this->error('Remise à zéro impossible :');

            foreach ($plan['blockers'] as $reason) {
                $this->error('  - '.$reason);
            }

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->info('Simulation : rien n\'a été effacé.');
            $this->line("Pour effacer : php artisan app:reset-learners --database={$connected} --force --confirm=\"{$expected}\"".($this->option('include-granted') ? ' --include-granted' : '').($this->option('notify') ? ' --notify' : ''));

            return self::SUCCESS;
        }

        if ($this->option('database') !== $connected) {
            $this->error('--database ne correspond pas à la base connectée ('.$connected.') : rien n\'a été effacé.');

            return self::FAILURE;
        }

        if ($this->option('confirm') !== $expected) {
            $this->error("Confirmation absente ou inexacte : écrivez --confirm=\"{$expected}\". Rien n'a été effacé.");

            return self::FAILURE;
        }

        try {
            $result = $reset->run();
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Remise à zéro faite : '.array_sum($result['rows']).' ligne(s) et '.$result['files'].' fichier(s) effacés.');
        $this->line('Sauvegarde (disque privé) : '.$result['backup']);
        $this->line('Retour en arrière : php artisan app:restore-learners '.$result['backup']);

        if ($this->option('notify')) {
            $queued = LearnerInvitations::queue($result['users']);
            $days = (int) ceil($queued['queued'] / max(1, (int) config('platform.courses.review.daily_emails')));
            $this->info("Invitations par e-mail : {$queued['queued']} en file d'attente, {$queued['no_email']} compte(s) sans adresse e-mail (rien n'est envoyé).");
            $this->line('Envoi chaque heure (learners:send-invitations), dans la limite d\'e-mails du jour'.($days > 1 ? " : environ {$days} jours." : '.').' Suivi : administration, « Alertes ».');
        }

        return self::SUCCESS;
    }
}
