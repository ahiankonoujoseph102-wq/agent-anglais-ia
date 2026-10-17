<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

/**
 * Crée un administrateur, ou promeut un compte existant.
 * Il n'existe volontairement aucun moyen de devenir administrateur depuis le site.
 *
 * Le numéro et le mot de passe ne sont JAMAIS des options de la commande (ils resteraient dans
 * l'historique du terminal ou de Laravel Cloud) et ne sont jamais affichés ni journalisés :
 *  - en terminal, la commande les demande (le mot de passe est saisi sans affichage, deux fois) ;
 *  - sans terminal (onglet « Commands » de Laravel Cloud), elle les lit dans les variables
 *    temporaires ADMIN_PHONE et ADMIN_PASSWORD (config/admin.php), à supprimer ensuite.
 */
class CreateAdmin extends Command
{
    public const MIN_PASSWORD_LENGTH = 10;

    protected $signature = 'app:create-admin
        {--name= : Nom de l\'administrateur (« Administrateur » par défaut)}';

    protected $description = 'Crée un compte administrateur ou promeut un compte existant (numéro et mot de passe demandés)';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $rawPhone = $interactive ? $this->ask('Numéro de téléphone') : config('admin.bootstrap.phone');
        $phone = PhoneNumber::normalize((string) $rawPhone);

        if ($phone === null) {
            $this->error($interactive
                ? 'Numéro de téléphone invalide.'
                : 'Numéro absent ou invalide : sans terminal, définissez ADMIN_PHONE et ADMIN_PASSWORD (voir docs/deploiement-laravel-cloud.md).');

            return self::FAILURE;
        }

        $user = User::firstWhere('phone', $phone);

        $password = $interactive
            ? $this->askPassword($user !== null)
            : config('admin.bootstrap.password');

        if ($password === false) {
            return self::FAILURE;
        }

        if (($user === null || filled($password)) && mb_strlen((string) $password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('Le mot de passe doit contenir au moins '.self::MIN_PASSWORD_LENGTH.' caractères.');

            return self::FAILURE;
        }

        if ($user === null) {
            $user = new User(['phone' => $phone]);
            $user->name = $this->option('name') ?: 'Administrateur';
        }

        if (filled($password)) {
            $user->password = $password;
        }

        $user->role = Role::Admin;
        $user->save();

        // Ni le mot de passe ni le numéro complet ne sont écrits : la sortie peut être conservée par l'hébergeur.
        $this->info(($user->wasRecentlyCreated ? 'Administrateur créé' : 'Compte promu administrateur').' : '.$this->mask($phone));

        if (! $interactive) {
            $this->warn('Supprimez maintenant ADMIN_PHONE et ADMIN_PASSWORD des variables d\'environnement, puis redéployez.');
        }

        return self::SUCCESS;
    }

    /**
     * Mot de passe saisi sans affichage, deux fois. Chaîne vide = conserver celui d'un compte existant ;
     * false = les deux saisies diffèrent.
     */
    private function askPassword(bool $exists): string|false
    {
        $password = (string) $this->secret($exists
            ? 'Nouveau mot de passe (laisser vide pour conserver l\'actuel)'
            : 'Mot de passe ('.self::MIN_PASSWORD_LENGTH.' caractères au minimum)');

        if ($password === '' && $exists) {
            return '';
        }

        if ($password !== (string) $this->secret('Confirmer le mot de passe')) {
            $this->error('Les deux mots de passe sont différents.');

            return false;
        }

        return $password;
    }

    private function mask(string $phone): string
    {
        return substr($phone, 0, 4).str_repeat('•', max(strlen($phone) - 6, 0)).substr($phone, -2);
    }
}
