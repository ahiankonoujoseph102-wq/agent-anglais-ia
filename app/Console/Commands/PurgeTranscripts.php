<?php

namespace App\Console\Commands;

use App\Support\Live\Transcripts;
use Illuminate\Console\Command;

class PurgeTranscripts extends Command
{
    protected $signature = 'transcripts:purge';

    protected $description = 'Efface le texte des conversations plus ancien que platform.transcripts.keep_days jours (90 par défaut)';

    public function handle(): int
    {
        $deleted = Transcripts::purge();

        $this->info($deleted.' réplique(s) effacée(s) (conversations de plus de '.(int) config('platform.transcripts.keep_days').' jours).');

        return self::SUCCESS;
    }
}
