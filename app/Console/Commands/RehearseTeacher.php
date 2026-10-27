<?php

namespace App\Console\Commands;

use App\Support\Gemini\TeacherRehearsal;
use Illuminate\Console\Command;

/**
 * Banc d'essai de Teacher Joe : joue les scénarios inventés de resources/teacher/rehearsal/scenarios.php avec
 * les consignes du professeur et vérifie chaque réponse. « --compare » joue aussi chaque scénario avec les
 * consignes d'avant l'amélioration d'octobre 2026. Aucune question : utilisable dans l'onglet Commands.
 * Coût : une demande au modèle texte par scénario (deux avec --compare).
 */
class RehearseTeacher extends Command
{
    protected $signature = 'teacher:rehearse {--compare : joue aussi chaque scénario avec les consignes d\'avant} {--only= : clés des scénarios, séparées par des virgules} {--pause=4 : secondes entre deux demandes (limite par minute du mode gratuit)}';

    protected $description = 'Banc d\'essai du professeur : scénarios inventés, réponses et vérifications automatiques';

    public function handle(): int
    {
        $scenarios = TeacherRehearsal::scenarios();

        if (filled($only = $this->option('only'))) {
            $keys = array_map('trim', explode(',', (string) $only));
            $unknown = array_diff($keys, array_keys($scenarios));

            if ($unknown !== []) {
                $this->error('Scénario inconnu : '.implode(', ', $unknown).'. Scénarios : '.implode(', ', array_keys($scenarios)).'.');

                return self::FAILURE;
            }

            $scenarios = array_intersect_key($scenarios, array_flip($keys));
        }

        $compare = (bool) $this->option('compare');
        $pause = max(0, (int) $this->option('pause'));
        $asked = 0;
        $totals = ['before' => [0, 0], 'after' => [0, 0]];

        foreach ($scenarios as $key => $scenario) {
            $this->newLine();
            $this->line('■ '.$scenario['title'].' ('.$key.')');
            $last = end($scenario['history']);
            $this->line('  Élève : '.$last[1]);

            foreach ($compare ? ['before', 'after'] : ['after'] as $version) {
                // Espacer les demandes : le même fournisseur répond avant et après (sinon le secours prendrait le relais).
                if ($asked++ > 0 && $pause > 0) {
                    sleep($pause);
                }

                $run = TeacherRehearsal::run($scenario, $version === 'before');
                $label = $version === 'before' ? 'AVANT' : ($compare ? 'APRÈS' : 'Réponse');

                if ($run['reply'] === null) {
                    $this->warn("  {$label} : aucun fournisseur n'a répondu (clé absente, limite ou panne).");

                    continue;
                }

                $this->line("  {$label} ({$run['provider']}) : ".str_replace("\n", ' ', $run['reply']));

                foreach ($run['checks'] as $check) {
                    $this->line('    '.($check['ok'] ? '✓' : '✗').' '.$check['label']);
                    $totals[$version][0] += $check['ok'] ? 1 : 0;
                    $totals[$version][1]++;
                }
            }
        }

        $this->newLine();

        if ($compare) {
            $this->line('Vérifications réussies AVANT : '.$totals['before'][0].' sur '.$totals['before'][1].'.');
        }

        $this->line('Vérifications réussies'.($compare ? ' APRÈS' : '').' : '.$totals['after'][0].' sur '.$totals['after'][1].'.');
        $this->line('Scénarios inventés, réponses du modèle texte : la séance réelle se fait à l\'oral, avec un autre modèle.');

        return self::SUCCESS;
    }
}
