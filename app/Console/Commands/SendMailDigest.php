<?php

namespace App\Console\Commands;

use App\Support\Alerts\MailDigest;
use Illuminate\Console\Command;

/** Résumé au fondateur des e-mails partis vers les élèves (chaque heure, seulement s'il y en a). */
class SendMailDigest extends Command
{
    protected $signature = 'mail:digest';

    protected $description = 'Envoie au fondateur le résumé des e-mails partis vers les élèves depuis le dernier résumé';

    public function handle(): int
    {
        $count = MailDigest::send();
        $this->info($count > 0 ? "Résumé envoyé : {$count} e-mail(s)." : 'Rien à résumer, ou résumé remis à plus tard.');

        return self::SUCCESS;
    }
}
