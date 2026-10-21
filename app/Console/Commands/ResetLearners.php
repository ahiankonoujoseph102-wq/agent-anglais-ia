<?php

namespace App\Console\Commands;

use App\Support\LearnerReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Remise à zéro des apprenants (après une grosse amélioration de Teacher Joe) : voir App\Support\LearnerReset.
 *
 * Sans --force : simulation, rien n'est touché. Pour effacer (l'onglet « Commands » de Laravel Cloud n'accepte
 * aucune question, tout passe par des options) :
 *   php artisan app:reset-learners --database=NOM --force --confirm="EFFACER N COMPTES"
 * Sauvegarde automatique avant l'effacement ; vérification : app:inspect-backup ; retour en arrière :
 * app:restore-learners. Relancée après une remise à zéro : « Rien à effacer », aucune sauvegarde vide, aucune
 * invitation remise en file. Deux exécutions en même temps : la seconde est refusée (verrou).
 */
class ResetLearners extends Command
{
    protected $signature = 'app:reset-learners
        {--database= : Nom de la base visée (vérifié contre la base connectée, obligatoire avec --force)}
        {--force : Effacer pour de vrai (sinon : simulation)}
        {--confirm= : Confirmation exacte : « EFFACER N COMPTES » (N = nombre de comptes d\'apprenants)}
        {--include-granted : Retirer aussi les accès offerts en cours (gardés par défaut)}
        {--include-test-payments : Avant le lancement : effacer aussi les paiements validés et refusés des apprenants (vos essais), leurs abonnements et leurs captures}
        {--notify : Inviter par e-mail les apprenants remis à zéro à refaire leur évaluation gratuite}';

    protected $description = 'Remet à zéro les apprenants (évaluation, programme, séances…) ; simulation sans --force';

    /** Verrou : deux exécutions en même temps sont impossibles (double toucher sur « Run », relance). */
    public const LOCK = 'app:reset-learners';

    private const NOTHING_ERASED = 'Cette exécution n\'a rien effacé.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            return $this->work();
        }

        // 30 minutes : durée maximale d'une commande de l'onglet « Commands ».
        $lock = Cache::lock(self::LOCK, 1800);

        if (! $lock->get()) {
            $this->error('Une remise à zéro est déjà en cours : '.self::NOTHING_ERASED.' Attendez sa fin, puis lancez la simulation (php artisan app:reset-learners) pour voir son résultat.');

            return self::FAILURE;
        }

        try {
            return $this->work();
        } finally {
            $lock->release();
        }
    }

    private function work(): int
    {
        $testPayments = (bool) $this->option('include-test-payments');
        $reset = new LearnerReset((bool) $this->option('include-granted'), $testPayments);
        $plan = $reset->plan();
        $connected = (string) DB::connection()->getDatabaseName();
        $expected = $reset->confirmationFor($plan);
        $last = LearnerReset::lastSummary();

        if ($last !== null) {
            $this->line($last);
        }

        $this->line('Base connectée : '.$connected);
        $this->line("Comptes d'apprenants : {$plan['accounts']} (les administrateurs ne sont jamais touchés).");
        $this->line("Niveaux et messages de bienvenue à remettre à zéro : {$plan['levels']} compte(s).");
        $this->line('Lignes à effacer :');

        foreach ($plan['rows'] as $table => $count) {
            $this->line("  {$table} : {$count}");
        }

        $this->line("Fichiers à effacer (Mes cours, documents de révision, attestations) : {$plan['files']}.");
        if ($testPayments) {
            $this->line('Paiements de test à effacer (validés et refusés), avec leurs abonnements et leurs captures : '.count($plan['payments']).'. Vérifiez que ce sont bien vos essais :');

            foreach ($plan['payments'] as $payment) {
                $this->line("  {$payment['date']} · {$payment['amount']} · {$payment['plan']} · {$payment['status']} · {$payment['learner']}");
            }

            $this->line('Gardé : comptes, sessions, données communes, journaux (lignes des alertes gardées, sans lien vers ces paiements).');
        } else {
            $this->line('Gardé : comptes, sessions, paiements et captures, données communes, journaux.');
        }

        $this->line($this->option('include-granted')
            ? "Accès offerts en cours à retirer : {$plan['granted_removed']}."
            : "Accès offerts en cours gardés : {$plan['granted_kept']} (--include-granted pour les retirer).");
        $this->line("Captures en attente de vérification : {$plan['pending_proofs']}.");

        if ($plan['blockers'] !== []) {
            $this->error('Remise à zéro impossible :');

            foreach ($plan['blockers'] as $reason) {
                $this->error('  - '.$reason);
            }

            $this->error(self::NOTHING_ERASED);

            return self::FAILURE;
        }

        // Plus rien à effacer (remise à zéro déjà faite, par exemple une commande relancée) : ni sauvegarde vide, ni
        // invitations remises en file, et ce n'est pas un échec.
        if (LearnerReset::isEmpty($plan)) {
            $this->info('Rien à effacer : aucune donnée d\'apprenant à remettre à zéro. '.self::NOTHING_ERASED.' Ne relancez pas la remise à zéro.');

            if ($this->option('notify')) {
                $this->line('Invitations : rien n\'est remis en file. Pour les mettre en file sans rien effacer : php artisan learners:queue-invitations '.(LearnerReset::last()['backup'] ?? 'backups/remise-a-zero-….json.gz'));
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->info('Simulation : rien n\'a été effacé.');
            $this->line("Pour effacer : php artisan app:reset-learners --database={$connected} --force --confirm=\"{$expected}\"".($testPayments ? ' --include-test-payments' : '').($this->option('include-granted') ? ' --include-granted' : '').($this->option('notify') ? ' --notify' : ''));

            return self::SUCCESS;
        }

        if ($this->option('database') !== $connected) {
            $this->error('--database ne correspond pas à la base connectée ('.$connected.') : '.self::NOTHING_ERASED);

            return self::FAILURE;
        }

        if ($this->option('confirm') !== $expected) {
            $this->error("Confirmation absente ou inexacte : écrivez --confirm=\"{$expected}\". ".self::NOTHING_ERASED);

            return self::FAILURE;
        }

        // run() revérifie la confirmation sur des données relues : toute exception veut dire « rien d'effacé ».
        try {
            $result = $reset->run((string) $this->option('confirm'), (bool) $this->option('notify'));
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage().' '.self::NOTHING_ERASED);

            return self::FAILURE;
        }

        $this->info('Remise à zéro faite.');
        $this->line("Niveaux et messages de bienvenue remis à zéro : {$result['levels']} compte(s).");
        $this->line('Lignes effacées : '.array_sum($result['rows']).'.');

        foreach ($result['rows'] as $table => $count) {
            $this->line("  {$table} : {$count}");
        }

        $this->line("Fichiers effacés : {$result['files']}.");

        if ($testPayments) {
            $this->line("Paiements de test effacés : {$result['payments']}.");
        }

        $this->line('Sauvegarde (disque privé) : '.$result['backup']);
        $this->line('Vérifier la sauvegarde : php artisan app:inspect-backup '.$result['backup']);
        $this->line('Retour en arrière : php artisan app:restore-learners '.$result['backup']);

        if ($result['invitations'] !== null) {
            $queued = $result['invitations'];
            $days = (int) ceil($queued['queued'] / max(1, (int) config('platform.courses.review.daily_emails')));
            $this->info("Invitations par e-mail : {$queued['queued']} en file d'attente, {$queued['no_email']} compte(s) sans adresse e-mail (rien n'est envoyé)".($queued['already_sent'] > 0 ? ", {$queued['already_sent']} déjà envoyée(s) (jamais en double)" : '').'.');
            $this->line('Envoi chaque heure (learners:send-invitations), dans la limite d\'e-mails du jour'.($days > 1 ? " : environ {$days} jours." : '.').' Suivi : administration, « Alertes ».');
        }

        $this->line('Ne relancez pas cette commande : une nouvelle simulation dira « Rien à effacer ».');

        if ($result['file_errors'] !== []) {
            $this->error('L\'effacement a eu lieu, mais '.count($result['file_errors']).' fichier(s) n\'ont pas pu être effacés du disque privé (ils sont aussi dans la sauvegarde) :');

            foreach ($result['file_errors'] as $file) {
                $this->error('  - '.$file);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
