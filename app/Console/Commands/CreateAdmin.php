<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

/**
 * Crée un administrateur, ou promeut un compte existant.
 * Il n'existe volontairement aucun moyen de devenir administrateur depuis le site.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nom de l\'administrateur}
        {--phone= : Numéro de téléphone}
        {--password= : Mot de passe (8 caractères minimum)}';

    protected $description = 'Crée un compte administrateur ou promeut un compte existant';

    public function handle(): int
    {
        $phone = PhoneNumber::normalize($this->option('phone') ?? $this->ask('Numéro de téléphone'));

        if ($phone === null) {
            $this->error('Numéro de téléphone invalide.');

            return self::FAILURE;
        }

        $user = User::firstWhere('phone', $phone);
        $password = $this->option('password') ?? $this->secret($user ? 'Nouveau mot de passe (laisser vide pour le conserver)' : 'Mot de passe');

        if (($user === null || filled($password)) && mb_strlen((string) $password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        if ($user === null) {
            $user = new User(['phone' => $phone]);
            $user->name = $this->option('name') ?? $this->ask('Nom', 'Administrateur');
        }

        if (filled($password)) {
            $user->password = $password;
        }

        $user->role = Role::Admin;
        $user->save();

        $this->info(($user->wasRecentlyCreated ? 'Administrateur créé' : 'Compte promu administrateur').' : '.$user->phone);

        return self::SUCCESS;
    }
}
