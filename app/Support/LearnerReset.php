<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Support\Gemini\WelcomeMessage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Remise à zéro des apprenants (commande app:reset-learners, restauration par app:restore-learners).
 *
 * Effacé pour les comptes « learner » (jamais un administrateur) : évaluations, programmes et leurs leçons,
 * séances, erreurs à revoir, bilans de niveau, attestations (et leur PDF), texte et événements des conversations,
 * « Mes cours » (leçons, pages, parties, séances, documents de révision, fichiers), abonnements terminés ou
 * retirés ; niveau et message de bienvenue du compte remis à zéro ; salutations de bienvenue gardées (par prénom).
 * Gardé : le compte, ses sessions, TOUS les paiements et leurs captures, les accès offerts en cours (sauf
 * « includeGranted »), les données communes (tableaux des leçons, messages vocaux) et les journaux.
 *
 * Refusé tant qu'un apprenant a un paiement validé, un abonnement payé en cours, un accès provisoire en cours, une
 * capture en attente de vérification ou une séance en cours. Tout ce qui est effacé est d'abord sauvegardé sur le
 * disque privé : backups/remise-a-zero-AAAA-MM-JJ-HHMM.json.gz et les fichiers dans le dossier du même nom.
 */
class LearnerReset
{
    public const BACKUP_DIRECTORY = 'backups';

    /** Tables effacées, dans l'ordre de l'effacement (les dépendantes d'abord). */
    public const TABLES = [
        'course_reviews', 'course_sessions', 'course_pages', 'course_parts', 'course_lessons',
        'conversation_events', 'conversation_lines', 'learner_errors', 'attestations', 'lessons', 'level_tests',
        'program_lessons', 'programs', 'assessments', 'subscriptions',
    ];

    /** Séances en direct (une séance ouverte bloque la remise à zéro). */
    private const LIVE_TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function __construct(private bool $includeGranted = false) {}

    /** @return list<int> */
    public function learnerIds(): array
    {
        return DB::table('users')->where('role', Role::Learner->value)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Ce qui empêche la remise à zéro : une phrase par raison (vide = rien ne l'empêche).
     *
     * @return list<string>
     */
    public function blockers(): array
    {
        $ids = $this->learnerIds();
        $reasons = [];
        $count = fn ($query) => $query->count();

        $validated = $count(DB::table('payments')->whereIn('user_id', $ids)->where('status', PaymentStatus::Validated->value));
        $pending = $count(DB::table('payments')->whereIn('user_id', $ids)->where('status', PaymentStatus::Pending->value));
        $paid = $count(DB::table('subscriptions')->whereIn('user_id', $ids)->whereNull('granted_days')
            ->where('status', SubscriptionStatus::Active->value)->where('ends_at', '>', now()));
        $provisional = $count(DB::table('subscriptions')->whereIn('user_id', $ids)
            ->where('status', SubscriptionStatus::Provisional->value)->where('provisional_until', '>', now()));
        $live = array_sum(array_map(fn (string $table) => $count(DB::table($table)->whereIn('user_id', $ids)
            ->whereNotNull('started_at')->whereNull('ended_at')), self::LIVE_TABLES));

        if ($validated > 0) {
            $reasons[] = "{$validated} paiement(s) validé(s) : des apprenants ont payé, la remise à zéro est refusée.";
        }

        if ($paid > 0) {
            $reasons[] = "{$paid} abonnement(s) payé(s) en cours.";
        }

        if ($provisional > 0) {
            $reasons[] = "{$provisional} accès provisoire(s) de 24 heures en cours.";
        }

        if ($pending > 0) {
            $reasons[] = "{$pending} capture(s) de paiement en attente de vérification : validez-les ou refusez-les d'abord dans l'administration (Paiements en attente).";
        }

        if ($live > 0) {
            $reasons[] = "{$live} séance(s) en cours : attendez qu'elles soient finies.";
        }

        return $reasons;
    }

    /**
     * Lignes concernées, table par table (simulation et sauvegarde).
     *
     * @return array<string, Builder>
     */
    private function queries(): array
    {
        $ids = $this->learnerIds();
        $byUser = fn (string $table) => DB::table($table)->whereIn('user_id', $ids);

        $subscriptions = $byUser('subscriptions')->where(function ($query) {
            $query->whereIn('status', [SubscriptionStatus::Expired->value, SubscriptionStatus::Cancelled->value, SubscriptionStatus::Suspended->value]);

            if ($this->includeGranted) {
                $query->orWhereNotNull('granted_days');
            }
        });

        return [
            'course_reviews' => $byUser('course_reviews'),
            'course_sessions' => $byUser('course_sessions'),
            'course_pages' => DB::table('course_pages')->whereIn('course_lesson_id', $byUser('course_lessons')->select('id')),
            'course_parts' => DB::table('course_parts')->whereIn('course_lesson_id', $byUser('course_lessons')->select('id')),
            'course_lessons' => $byUser('course_lessons'),
            'conversation_events' => $byUser('conversation_events'),
            'conversation_lines' => $byUser('conversation_lines'),
            'learner_errors' => $byUser('learner_errors'),
            'attestations' => $byUser('attestations'),
            'lessons' => $byUser('lessons'),
            'level_tests' => $byUser('level_tests'),
            'program_lessons' => DB::table('program_lessons')->whereIn('program_id', $byUser('programs')->select('id')),
            'programs' => $byUser('programs'),
            'assessments' => $byUser('assessments'),
            'subscriptions' => $subscriptions,
        ];
    }

    /**
     * Fichiers du disque privé effacés (et sauvegardés) : « Mes cours », documents de révision, attestations.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $disk = PrivateStorage::disk();
        $files = [];

        foreach ($this->learnerIds() as $id) {
            foreach ([trim((string) config('platform.courses.directory'), '/'), trim((string) config('platform.courses.review.directory'), '/')] as $directory) {
                array_push($files, ...$disk->allFiles("{$directory}/{$id}"));
            }
        }

        foreach ($this->queries()['attestations']->pluck('code') as $code) {
            if ($disk->exists("attestations/{$code}.pdf")) {
                $files[] = "attestations/{$code}.pdf";
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Simulation : ce qui serait effacé et ce qui est gardé.
     *
     * @return array{accounts: int, rows: array<string, int>, files: int, levels: int, pending_proofs: int, granted_kept: int, granted_removed: int, blockers: list<string>}
     */
    public function plan(): array
    {
        $ids = $this->learnerIds();
        $granted = DB::table('subscriptions')->whereIn('user_id', $ids)->whereNotNull('granted_days')
            ->where('status', SubscriptionStatus::Active->value)->where('ends_at', '>', now())->count();

        return [
            'accounts' => count($ids),
            'rows' => array_map(fn ($query) => (clone $query)->count(), $this->queries()),
            'files' => count($this->files()),
            'levels' => DB::table('users')->whereIn('id', $ids)->where(fn ($query) => $query->whereNotNull('level')->orWhereNotNull('welcomed_at'))->count(),
            'pending_proofs' => DB::table('payments')->whereIn('user_id', $ids)->where('status', PaymentStatus::Pending->value)->count(),
            'granted_kept' => $this->includeGranted ? 0 : $granted,
            'granted_removed' => $this->includeGranted ? $granted : 0,
            'blockers' => $this->blockers(),
        ];
    }

    public static function confirmation(int $accounts): string
    {
        return "EFFACER {$accounts} COMPTES";
    }

    /**
     * Sauvegarde, puis effacement. Renvoie le chemin de la sauvegarde.
     *
     * @return array{backup: string, rows: array<string, int>, files: int, users: list<int>}
     */
    public function run(): array
    {
        if ($this->blockers() !== []) {
            throw new RuntimeException(implode(' ', $this->blockers()));
        }

        $disk = PrivateStorage::disk();
        $name = self::BACKUP_DIRECTORY.'/remise-a-zero-'.now()->format('Y-m-d-Hi');

        if ($disk->exists($name.'.json.gz')) {
            $name .= now()->format('s');
        }

        $ids = $this->learnerIds();
        $queries = $this->queries();
        $files = $this->files();
        $data = [
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'database' => DB::connection()->getDatabaseName(),
            'include_granted' => $this->includeGranted,
            'users' => DB::table('users')->whereIn('id', $ids)->get(['id', 'level', 'welcomed_at'])->map(fn ($row) => (array) $row)->all(),
            // Liens des paiements gardés vers les abonnements effacés (remis à vide par la base).
            'payment_links' => DB::table('payments')->whereIn('subscription_id', (clone $queries['subscriptions'])->select('id'))
                ->get(['id', 'subscription_id'])->map(fn ($row) => (array) $row)->all(),
            'tables' => array_map(fn ($query) => (clone $query)->get()->map(fn ($row) => (array) $row)->all(), $queries),
            'files' => $files,
        ];

        // 1. Sauvegarde (fichiers compris) : rien n'est effacé si elle échoue.
        foreach ($files as $file) {
            $this->copy($disk, $file, $name.'/'.$file);
        }

        if (! $disk->put($name.'.json.gz', (string) gzencode((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 9))) {
            throw new RuntimeException('La sauvegarde n\'a pas pu être écrite sur le disque privé : rien n\'a été effacé.');
        }

        // 2. Effacement, d'un seul tenant.
        $rows = [];
        DB::transaction(function () use ($queries, $ids, &$rows) {
            DB::table('course_lessons')->whereIn('user_id', $ids)->update(['current_part_id' => null]);

            foreach (self::TABLES as $table) {
                $keys = (clone $queries[$table])->pluck('id')->all();
                $rows[$table] = 0;

                foreach (array_chunk($keys, 500) as $chunk) {
                    $rows[$table] += DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }

            DB::table('users')->whereIn('id', $ids)->update(['level' => null, 'welcomed_at' => null]);
        });

        foreach ($files as $file) {
            $disk->delete($file);
        }

        // Salutations de bienvenue (une par prénom) : refaites à la prochaine ouverture d'un accès.
        $disk->deleteDirectory(WelcomeMessage::DIRECTORY.'/'.WelcomeMessage::OPENINGS);

        return ['backup' => $name.'.json.gz', 'rows' => $rows, 'files' => count($files), 'users' => $ids];
    }

    /**
     * Remet en place une sauvegarde : lignes (mêmes identifiants), niveau et bienvenue des comptes, liens des
     * paiements, fichiers. Refusée si une ligne existe déjà (rien n'est écrasé).
     *
     * @return array{rows: array<string, int>, files: int}
     */
    public static function restore(string $path): array
    {
        $disk = PrivateStorage::disk();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Sauvegarde introuvable sur le disque privé : {$path}");
        }

        $data = json_decode((string) gzdecode((string) $disk->get($path)), true, flags: JSON_THROW_ON_ERROR);

        if (($data['version'] ?? null) !== 1) {
            throw new RuntimeException('Format de sauvegarde inconnu.');
        }

        $rows = [];
        DB::transaction(function () use ($data, &$rows) {
            // Ordre inverse de l'effacement : les tables dont les autres dépendent d'abord.
            foreach (array_reverse(self::TABLES) as $table) {
                $lines = $data['tables'][$table] ?? [];
                $existing = DB::table($table)->whereIn('id', array_column($lines, 'id'))->count();

                if ($existing > 0) {
                    throw new RuntimeException("{$existing} ligne(s) de « {$table} » existent déjà : la sauvegarde n'est pas remise, rien n'a changé.");
                }

                // Leçons « Mes cours » : partie choisie remise après les parties.
                $insert = $table === 'course_lessons' ? array_map(fn ($line) => ['current_part_id' => null] + $line, $lines) : $lines;

                foreach (array_chunk($insert, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $rows[$table] = count($lines);
            }

            foreach ($data['tables']['course_lessons'] ?? [] as $line) {
                DB::table('course_lessons')->where('id', $line['id'])->update(['current_part_id' => $line['current_part_id']]);
            }

            foreach ($data['users'] as $user) {
                DB::table('users')->where('id', $user['id'])->update(['level' => $user['level'], 'welcomed_at' => $user['welcomed_at']]);
            }

            foreach ($data['payment_links'] as $link) {
                DB::table('payments')->where('id', $link['id'])->whereNull('subscription_id')->update(['subscription_id' => $link['subscription_id']]);
            }
        });

        $folder = substr($path, 0, -strlen('.json.gz'));
        $files = 0;

        foreach ($data['files'] as $file) {
            if ($disk->exists($folder.'/'.$file) && ! $disk->exists($file)) {
                (new self)->copy($disk, $folder.'/'.$file, $file);
                $files++;
            }
        }

        return ['rows' => $rows, 'files' => $files];
    }

    private function copy(Filesystem $disk, string $from, string $to): void
    {
        $content = $disk->get($from);

        if ($content === null || ! $disk->put($to, $content)) {
            throw new RuntimeException("Le fichier {$from} n'a pas pu être sauvegardé : rien n'a été effacé.");
        }
    }
}
