<?php

namespace App\Console\Commands;

use App\Support\Admin\AdminRecovery;
use Illuminate\Console\Command;

/**
 * Envoie à l'adresse de récupération (config admin.recovery.email) un lien à usage unique pour que
 * l'administrateur retrouve l'accès. Aucune question : utilisable depuis l'onglet « Commands » de
 * Laravel Cloud. La sortie ne montre JAMAIS le lien ni un mot de passe, seulement l'adresse masquée.
 */
class SendAdminRecovery extends Command
{
    protected $signature = 'app:send-admin-recovery';

    protected $description = 'Envoie par e-mail un lien à usage unique (30 minutes) pour retrouver l\'accès administrateur';

    public function handle(): int
    {
        [$sent, $message] = AdminRecovery::send();

        $sent ? $this->info($message) : $this->error($message);

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
