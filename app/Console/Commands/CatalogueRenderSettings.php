<?php

namespace App\Console\Commands;

use App\Support\Videos\ScreenCatalogue;
use Illuminate\Console\Command;

/**
 * Réglages de l'image de Teacher Joe pour le catalogue des écrans, en JSON (tools/presentation/catalogue.mjs, sur le
 * site LOCAL de démonstration : la répétition locale n'a pas besoin de Talkami).
 */
class CatalogueRenderSettings extends Command
{
    protected $signature = 'videos:catalogue-render';

    protected $description = 'Réglages de l\'image de Teacher Joe pour le catalogue des écrans (JSON).';

    public function handle(): int
    {
        $this->line((string) json_encode(ScreenCatalogue::render(), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
