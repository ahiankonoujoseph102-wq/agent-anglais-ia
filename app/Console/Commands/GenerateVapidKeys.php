<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'alerts:generate-vapid';

    protected $description = 'Crée les clés VAPID des notifications de l\'administrateur (à copier dans Laravel Cloud, jamais dans le dépôt)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:votre-adresse@exemple.com');
        $this->newLine();
        $this->warn('Copiez ces trois lignes dans les variables de Laravel Cloud, puis redéployez. Ne les envoyez à personne et ne les écrivez dans aucun fichier du dépôt.');

        return self::SUCCESS;
    }
}
