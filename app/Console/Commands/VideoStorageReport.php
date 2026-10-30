<?php

namespace App\Console\Commands;

use App\Support\Videos\VideoStorage;
use Illuminate\Console\Command;

/**
 * Stockage des vidéos : sans option, LECTURE SEULE (poids par dossier, coût par mois, liste de ce qui peut être effacé
 * et la commande exacte à lancer). Avec --delete --confirm="SUPPRIMER N FICHIERS" : efface cette liste, seulement après
 * l'accord du fondateur. Aucune question (l'onglet Commands de Laravel Cloud n'en accepte pas).
 */
class VideoStorageReport extends Command
{
    protected $signature = 'videos:storage
        {--delete : Effacer la liste proposée (avec --confirm)}
        {--confirm= : Phrase de confirmation, recopiée de la simulation}';

    protected $description = 'Stockage des vidéos : poids, coût et nettoyage proposé (lecture seule sans --delete)';

    public function handle(VideoStorage $storage): int
    {
        $this->line('Stockage des vidéos, par dossier :');
        $total = 0;

        foreach ($storage->usage() as $directory => $usage) {
            $total += $usage['bytes'];
            $this->line(sprintf('  %s : %d fichiers, %s', $directory, $usage['files'], $this->size($usage['bytes'])));
        }

        $this->line(sprintf('Total : %s, soit environ %s $ par mois.', $this->size($total), number_format(VideoStorage::monthlyCost($total), 3, ',', ' ')));
        $this->newLine();

        $groups = $storage->proposal();
        $count = 0;
        $bytes = 0;
        $this->line('Peut être effacé :');

        foreach ($groups as $group) {
            $groupBytes = array_sum(array_column($group['files'], 'bytes'));
            $count += count($group['files']);
            $bytes += $groupBytes;
            $this->line(sprintf('  - %s : %d fichiers, %s', $group['label'], count($group['files']), $this->size($groupBytes)));
        }

        $this->line(sprintf('Total à effacer : %d fichiers, %s (économie d\'environ %s $ par mois).', $count, $this->size($bytes), number_format(VideoStorage::monthlyCost($bytes), 3, ',', ' ')));
        $this->line('Toujours gardé : versions « site » de « Comment ça marche », voix des présentations, vidéos pas encore envoyées par e-mail, extraits des voix.');

        if (! $this->option('delete')) {
            $this->newLine();
            $this->line('Rien n\'a été effacé (simulation). Pour effacer cette liste, après accord :');
            $this->line('php artisan videos:storage --delete --confirm="'.$storage->confirmation().'"');

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->line('Rien à effacer.');

            return self::SUCCESS;
        }

        if ($this->option('confirm') !== $storage->confirmation()) {
            $this->error('Confirmation absente ou différente : rien n\'a été effacé. Phrase attendue : '.$storage->confirmation());

            return self::FAILURE;
        }

        $result = $storage->delete();
        $this->info(sprintf('Effacé : %d fichiers, %s.', $result['files'], $this->size($result['bytes'])));

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? number_format($bytes / 1024 ** 3, 2, ',', ' ').' gigaoctets'
            : number_format($bytes / 1024 ** 2, 1, ',', ' ').' mégaoctets';
    }
}
