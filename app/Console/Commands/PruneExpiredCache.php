<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cache en base de données (CACHE_STORE=database) : Laravel n'efface une entrée expirée que si on la relit. Les clés
 * lues une seule fois (limites d'essais par adresse, mises de côté d'un fournisseur, repères d'un jour…) restaient
 * donc pour toujours dans la table. Effacées chaque nuit, avec les verrous expirés.
 */
class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Efface les entrées expirées du cache en base de données (et les verrous expirés)';

    public function handle(): int
    {
        $store = (string) config('cache.default');
        $config = (array) config('cache.stores.'.$store, []);

        if (($config['driver'] ?? null) !== 'database') {
            $this->info('Le cache n\'est pas en base de données : rien à effacer.');

            return self::SUCCESS;
        }

        $connection = DB::connection($config['connection'] ?? null);
        $now = now()->getTimestamp();
        $table = (string) ($config['table'] ?? 'cache');
        $deleted = $connection->table($table)->where('expiration', '<=', $now)->delete();

        $lockConnection = DB::connection($config['lock_connection'] ?? $config['connection'] ?? null);
        $locks = $lockConnection->table((string) ($config['lock_table'] ?? 'cache_locks'))->where('expiration', '<=', $now)->delete();

        $this->info($deleted.' entrée(s) et '.$locks.' verrou(s) expirés effacés.');

        return self::SUCCESS;
    }
}
