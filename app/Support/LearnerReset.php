<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Gemini\WelcomeMessage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

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
 * capture en attente de vérification ou une séance en cours. Avant le lancement seulement, « includeTestPayments »
 * (option --include-test-payments) lève les deux premiers refus : les paiements validés et refusés des apprenants
 * (des essais), tous leurs abonnements payés et leurs captures sont alors effacés aussi (sauvegardés d'abord), et les
 * lignes du journal des alertes perdent leur lien vers ces paiements (gardées). Tout ce qui est effacé est d'abord sauvegardé sur le
 * disque privé : backups/remise-a-zero-AAAA-MM-JJ-HHMM.json.gz et les fichiers dans le dossier du même nom.
 */
class LearnerReset
{
    public const BACKUP_DIRECTORY = 'backups';

    /** Réglage (table settings) qui garde la dernière remise à zéro faite. */
    public const LAST_RESET_KEY = 'learner_reset.last';

    /** Tables effacées, dans l'ordre de l'effacement (les dépendantes d'abord). */
    public const TABLES = [
        'course_reviews', 'course_sessions', 'course_pages', 'course_parts', 'course_lessons',
        'conversation_events', 'conversation_lines', 'learner_errors', 'attestations', 'lessons', 'level_tests',
        'program_lessons', 'programs', 'assessments', 'payments', 'subscriptions',
    ];

    /** Séances en direct (une séance ouverte bloque la remise à zéro). */
    private const LIVE_TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function __construct(private bool $includeGranted = false, private bool $includeTestPayments = false) {}

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

        // Paiements de test, effacés avec l'option : plus un refus.
        if ($this->includeTestPayments) {
            $validated = $paid = 0;
        }

        if ($validated > 0) {
            $reasons[] = "{$validated} paiement(s) validé(s) : des apprenants ont payé, la remise à zéro est refusée. S'il ne s'agit que de vos paiements de test, ajoutez --include-test-payments pour les voir, puis les effacer aussi.";
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

            // Paiements de test : tous les abonnements payés aussi, même en cours (jamais un accès provisoire en cours,
            // qui refuse la remise à zéro).
            if ($this->includeTestPayments) {
                $query->orWhere(fn ($paid) => $paid->whereNull('granted_days')->where('status', '!=', SubscriptionStatus::Provisional->value));
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
            // Paiements validés et refusés des apprenants, seulement avec l'option (jamais une capture en attente).
            'payments' => $byUser('payments')->whereIn('status', $this->includeTestPayments ? [PaymentStatus::Validated->value, PaymentStatus::Rejected->value] : []),
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

        // Captures des paiements de test.
        foreach ($this->queries()['payments']->whereNotNull('proof_path')->pluck('proof_path') as $proof) {
            if ($disk->exists($proof)) {
                $files[] = $proof;
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
     * @return array{accounts: int, rows: array<string, int>, files: int, levels: int, pending_proofs: int, granted_kept: int, granted_removed: int, payments: list<array{date: string, amount: string, plan: string, status: string, learner: string}>, blockers: list<string>}
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
            'payments' => $this->testPayments(),
            'blockers' => $this->blockers(),
        ];
    }

    /**
     * Paiements de test effacés (avec l'option), pour que l'administrateur vérifie que ce sont bien ses essais : date
     * (heure de l'application), montant, formule, statut, prénom et 4 derniers chiffres du numéro du compte. Jamais
     * de capture, de numéro de transaction ni de numéro complet.
     *
     * @return list<array{date: string, amount: string, plan: string, status: string, learner: string}>
     */
    public function testPayments(): array
    {
        return Payment::query()->whereIn('id', $this->queries()['payments']->select('id'))->with('user')->orderBy('created_at')->get()
            ->map(fn (Payment $payment) => [
                'date' => $payment->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'amount' => Money::format($payment->amount),
                'plan' => (string) $payment->plan_name,
                'status' => $payment->status->label(),
                'learner' => Greeting::firstName((string) $payment->user?->name).' · …'.substr(preg_replace('/\D/', '', (string) $payment->user?->phone), -4),
            ])->all();
    }

    public static function confirmation(int $accounts, ?int $payments = null): string
    {
        return "EFFACER {$accounts} COMPTES".($payments === null ? '' : " ET {$payments} PAIEMENTS");
    }

    /** Confirmation attendue pour ce plan (les paiements de test en font partie avec l'option). */
    public function confirmationFor(array $plan): string
    {
        return self::confirmation($plan['accounts'], $this->includeTestPayments ? count($plan['payments']) : null);
    }

    /** Plus rien à effacer : aucune ligne, aucun fichier, aucun niveau ni message de bienvenue (remise à zéro déjà faite). */
    public static function isEmpty(array $plan): bool
    {
        return array_sum($plan['rows']) === 0 && $plan['files'] === 0 && $plan['levels'] === 0;
    }

    /**
     * Dernière remise à zéro faite (enregistrée dans la même transaction que l'effacement : si elle existe,
     * l'effacement a eu lieu), ou null.
     *
     * @return array{at: string, backup: string, accounts: int, levels: int, rows: int, files: int, payments: int, invitations: array{queued: int, no_email: int, already_sent: int}|null}|null
     */
    public static function last(): ?array
    {
        $value = Setting::get(self::LAST_RESET_KEY);
        $data = $value === null ? null : json_decode($value, true);

        return is_array($data) ? $data : null;
    }

    /** Une ligne lisible sur la dernière remise à zéro (heure de l'application), ou null. */
    public static function lastSummary(): ?string
    {
        $last = self::last();

        if ($last === null) {
            return null;
        }

        $at = Carbon::parse($last['at'])->timezone(config('app.timezone'))->format('d/m/Y à H:i');
        $invitations = $last['invitations'] === null
            ? 'aucune invitation demandée'
            : "invitations : {$last['invitations']['queued']} mise(s) en file, {$last['invitations']['no_email']} sans adresse e-mail";

        $restored = isset($last['restored_at'])
            ? ' (sauvegarde remise en place le '.Carbon::parse($last['restored_at'])->timezone(config('app.timezone'))->format('d/m/Y à H:i').')'
            : '';

        return "Dernière remise à zéro faite le {$at} : {$last['levels']} niveau(x) remis à zéro, {$last['rows']} ligne(s), {$last['files']} fichier(s) et {$last['payments']} paiement(s) effacés ; {$invitations}. Sauvegarde : {$last['backup']}{$restored}.";
    }

    /**
     * Sauvegarde, puis effacement. La confirmation est recalculée et vérifiée ICI, juste avant la sauvegarde : une
     * confirmation qui ne correspond plus aux données (par exemple après une première exécution) n'efface rien.
     * Tout ce qui lève une exception a lieu AVANT l'effacement ou l'annule (transaction) : une exception veut
     * toujours dire « rien n'a été effacé ». Après l'effacement, un fichier qui ne s'efface pas est seulement noté
     * (« file_errors »). Les invitations (« notify ») et le repère de la dernière remise à zéro sont écrits dans la
     * même transaction que l'effacement.
     *
     * @return array{backup: string, rows: array<string, int>, files: int, file_errors: list<string>, levels: int, payments: int, users: list<int>, invitations: array{queued: int, no_email: int, already_sent: int}|null}
     */
    public function run(string $confirmation, bool $notify = false): array
    {
        if ($this->blockers() !== []) {
            throw new RuntimeException(implode(' ', $this->blockers()));
        }

        $plan = $this->plan();

        if (self::isEmpty($plan)) {
            throw new RuntimeException('Rien à effacer : la remise à zéro est déjà faite.');
        }

        $expected = $this->confirmationFor($plan);

        if ($confirmation !== $expected) {
            throw new RuntimeException("Confirmation absente ou inexacte : écrivez --confirm=\"{$expected}\".");
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
            'include_test_payments' => $this->includeTestPayments,
            // Lignes du journal des alertes liées aux paiements de test : gardées, sans le lien.
            'alert_links' => DB::table('admin_alerts')->whereIn('payment_id', (clone $queries['payments'])->select('id'))
                ->get(['id', 'payment_id'])->map(fn ($row) => (array) $row)->all(),
            'users' => DB::table('users')->whereIn('id', $ids)->get(['id', 'level', 'welcomed_at'])->map(fn ($row) => (array) $row)->all(),
            // Liens des paiements gardés vers les abonnements effacés (remis à vide par la base).
            'payment_links' => DB::table('payments')->whereIn('subscription_id', (clone $queries['subscriptions'])->select('id'))
                ->whereNotIn('id', (clone $queries['payments'])->select('id'))
                ->get(['id', 'subscription_id'])->map(fn ($row) => (array) $row)->all(),
            'tables' => array_map(fn ($query) => (clone $query)->get()->map(fn ($row) => (array) $row)->all(), $queries),
            'files' => $files,
        ];

        // 1. Sauvegarde (fichiers compris) : rien n'est effacé si elle échoue.
        foreach ($files as $file) {
            $this->copy($disk, $file, $name.'/'.$file);
        }

        if (! $disk->put($name.'.json.gz', (string) gzencode((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 9))) {
            throw new RuntimeException('La sauvegarde n\'a pas pu être écrite sur le disque privé.');
        }

        // 2. Effacement, d'un seul tenant, avec les invitations et le repère de la dernière remise à zéro.
        $rows = [];
        $levels = 0;
        $invitations = null;
        DB::transaction(function () use ($queries, $ids, $data, $name, $files, $notify, &$rows, &$levels, &$invitations) {
            DB::table('course_lessons')->whereIn('user_id', $ids)->update(['current_part_id' => null]);
            DB::table('admin_alerts')->whereIn('id', array_column($data['alert_links'], 'id'))->update(['payment_id' => null]);

            foreach (self::TABLES as $table) {
                $keys = (clone $queries[$table])->pluck('id')->all();
                $rows[$table] = 0;

                foreach (array_chunk($keys, 500) as $chunk) {
                    $rows[$table] += DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }

            $touched = DB::table('users')->whereIn('id', $ids)->where(fn ($query) => $query->whereNotNull('level')->orWhereNotNull('welcomed_at'));
            $levels = (clone $touched)->count();
            $touched->update(['level' => null, 'welcomed_at' => null]);

            if ($notify) {
                $invitations = LearnerInvitations::queue($ids);
            }

            Setting::put(self::LAST_RESET_KEY, (string) json_encode([
                'at' => now()->toIso8601String(),
                'backup' => $name.'.json.gz',
                'accounts' => count($ids),
                'levels' => $levels,
                'rows' => array_sum($rows),
                'files' => count($files),
                'payments' => $rows['payments'],
                'invitations' => $invitations,
            ], JSON_UNESCAPED_UNICODE));
        });

        // 3. Fichiers : l'effacement a eu lieu, un fichier qui résiste est seulement noté (il est dans la sauvegarde).
        $fileErrors = [];

        foreach ($files as $file) {
            try {
                if (! $disk->delete($file) && $disk->exists($file)) {
                    $fileErrors[] = $file;
                }
            } catch (Throwable) {
                $fileErrors[] = $file;
            }
        }

        // Salutations de bienvenue (une par prénom) : refaites à la prochaine ouverture d'un accès.
        try {
            $disk->deleteDirectory(WelcomeMessage::DIRECTORY.'/'.WelcomeMessage::OPENINGS);
        } catch (Throwable) {
            // Sans conséquence : elles seraient seulement refaites plus tard.
        }

        return [
            'backup' => $name.'.json.gz',
            'rows' => $rows,
            'files' => count($files) - count($fileErrors),
            'file_errors' => $fileErrors,
            'levels' => $levels,
            'payments' => $rows['payments'],
            'users' => $ids,
            'invitations' => $invitations,
        ];
    }

    /**
     * Contenu d'une sauvegarde (lecture seule).
     *
     * @return array<string, mixed>
     */
    public static function readBackup(string $path): array
    {
        $disk = PrivateStorage::disk();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Sauvegarde introuvable sur le disque privé : {$path}");
        }

        $data = json_decode((string) gzdecode((string) $disk->get($path)), true, flags: JSON_THROW_ON_ERROR);

        if (($data['version'] ?? null) !== 1) {
            throw new RuntimeException('Format de sauvegarde inconnu.');
        }

        return $data;
    }

    /**
     * Sauvegardes du disque privé, de la plus ancienne à la plus récente.
     *
     * @return list<string>
     */
    public static function backups(): array
    {
        $files = array_values(array_filter(PrivateStorage::disk()->files(self::BACKUP_DIRECTORY), fn (string $file) => str_ends_with($file, '.json.gz')));
        sort($files);

        return $files;
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
        $data = self::readBackup($path);

        $rows = [];
        DB::transaction(function () use ($data, $path, &$rows) {
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

            foreach ($data['alert_links'] ?? [] as $link) {
                DB::table('admin_alerts')->where('id', $link['id'])->whereNull('payment_id')->update(['payment_id' => $link['payment_id']]);
            }

            // Dernière remise à zéro : notée « remise en place ».
            $last = self::last();

            if ($last !== null && $last['backup'] === $path) {
                Setting::put(self::LAST_RESET_KEY, (string) json_encode(['restored_at' => now()->toIso8601String()] + $last, JSON_UNESCAPED_UNICODE));
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
