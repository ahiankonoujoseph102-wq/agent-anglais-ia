<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crée le jeton secret de la fabrication des vidéos : affiché UNE fois, jamais écrit dans un fichier. À coller dans
 * la variable VIDEO_UPLOAD_TOKEN de Laravel Cloud ET dans le secret TALKAMI_VIDEO_TOKEN de GitHub.
 */
class GenerateVideoToken extends Command
{
    protected $signature = 'videos:generate-token';

    protected $description = 'Crée le jeton secret de la fabrication des vidéos (affiché une seule fois)';

    public function handle(): int
    {
        $this->line('Jeton (affiché une seule fois, à copier tel quel) :');
        $this->line(Str::random(48));
        $this->line('1. Laravel Cloud : variable VIDEO_UPLOAD_TOKEN, puis Deploy.');
        $this->line('2. GitHub : secret TALKAMI_VIDEO_TOKEN du dépôt (Settings, Secrets and variables, Actions).');

        return self::SUCCESS;
    }
}
