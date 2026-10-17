<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\ProgramBuilder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour le développement local uniquement.
 * En production, créer l'administrateur avec « php artisan app:create-admin ».
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Ces comptes ont un mot de passe connu (« password ») : jamais en production.
        if (app()->isProduction()) {
            $this->command?->error('Données de démonstration refusées en production. Créez l\'administrateur avec « php artisan app:create-admin ».');

            return;
        }

        User::factory()->admin()->create([
            'name' => 'Administrateur',
            'phone' => '+22890000000',
        ]);

        $learner = User::factory()->level(Level::A2)->create([
            'name' => 'Apprenant Démo',
            'phone' => '+22890000001',
        ]);

        Subscription::factory()->for($learner)->forPlan('essentiel')->create();

        // Programme personnel déjà construit, comme après le questionnaire.
        app(ProgramBuilder::class)->build($learner, Level::A2, [
            'goal' => 'business',
            'occupation' => 'Commerçant au Grand Marché de Lomé',
            'interests' => 'football, musique',
            'study_days' => [1, 2, 3, 4, 5],
            'study_time' => '18:30',
            'difficulties' => ['speaking', 'pronunciation'],
        ]);

        User::factory(10)->create();
    }
}
