<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
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
        User::factory()->admin()->create([
            'name' => 'Administrateur',
            'phone' => '+22890000000',
        ]);

        $learner = User::factory()->level(Level::A2)->create([
            'name' => 'Apprenant Démo',
            'phone' => '+22890000001',
        ]);

        Subscription::factory()->for($learner)->forPlan('essentiel')->create();

        User::factory(10)->create();
    }
}
