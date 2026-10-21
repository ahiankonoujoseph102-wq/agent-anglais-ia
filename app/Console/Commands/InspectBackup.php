<?php

namespace App\Console\Commands;

use App\Models\LearnerInvitation;
use App\Support\LearnerReset;
use App\Support\PrivateStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecture seule : liste les sauvegardes de app:reset-learners et décrit l'une d'elles (la plus récente par défaut) :
 * lignes par table, comptes, fichiers et copies présentes, invitations et évaluations refaites depuis. Rien n'est
 * modifié, ni dans la base ni sur le disque privé.
 */
class InspectBackup extends Command
{
    protected $signature = 'app:inspect-backup
        {file? : Chemin de la sauvegarde (backups/remise-a-zero-….json.gz) ; la plus récente sinon}';

    protected $description = 'Décrit une sauvegarde de app:reset-learners (lecture seule)';

    public function handle(): int
    {
        $disk = PrivateStorage::disk();
        $backups = LearnerReset::backups();

        $this->line('Sauvegardes sur le disque privé : '.count($backups).'.');

        foreach ($backups as $backup) {
            $this->line("  {$backup} ({$disk->size($backup)} octets)");
        }

        $last = LearnerReset::lastSummary();
        $this->line($last ?? 'Aucune remise à zéro enregistrée.');

        $path = $this->argument('file') ?? end($backups);

        if (! is_string($path) || $path === '') {
            return self::SUCCESS;
        }

        try {
            $data = LearnerReset::readBackup($path);
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $folder = substr($path, 0, -strlen('.json.gz'));
        $missing = array_values(array_filter($data['files'], fn (string $file) => ! $disk->exists($folder.'/'.$file)));
        $users = collect($data['users']);
        $ids = $users->pluck('id')->all();

        $this->info("Sauvegarde : {$path}");
        $this->line('Faite le '.Carbon::parse($data['created_at'])->timezone(config('app.timezone'))->format('d/m/Y à H:i:s')." (base : {$data['database']}).");
        $this->line('Options : paiements de test '.($data['include_test_payments'] ?? false ? 'oui' : 'non').', accès offerts retirés '.($data['include_granted'] ? 'oui' : 'non').'.');
        $this->line('Paiements : '.count($data['tables']['payments'] ?? []).'.');
        $this->line('Évaluations : '.count($data['tables']['assessments'] ?? []).'.');
        $this->line("Comptes d'apprenants : {$users->count()} (avec un niveau : {$users->whereNotNull('level')->count()} ; message de bienvenue vu : {$users->whereNotNull('welcomed_at')->count()}).");
        $this->line('Fichiers : '.count($data['files']).' ; copies manquantes dans la sauvegarde : '.count($missing).'.');

        foreach (array_slice($missing, 0, 10) as $file) {
            $this->error('  manquante : '.$file);
        }

        $this->line('Lignes par table :');

        foreach ($data['tables'] as $table => $lines) {
            $this->line("  {$table} : ".count($lines));
        }

        $invitations = LearnerInvitation::query()->whereIn('user_id', $ids)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $this->line('Invitations de ces comptes aujourd\'hui : '.($invitations->isEmpty() ? 'aucune' : $invitations->map(fn ($total, $status) => "{$status} {$total}")->implode(', ')).'.');
        $this->line('Évaluations refaites depuis par ces comptes : '.DB::table('assessments')->whereIn('user_id', $ids)->count().' (une restauration serait refusée tant qu\'il y en a).');

        return self::SUCCESS;
    }
}
