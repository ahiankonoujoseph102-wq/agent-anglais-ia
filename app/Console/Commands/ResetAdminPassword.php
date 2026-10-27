<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mot de passe oublié de l'ADMINISTRATEUR (la récupération par WhatsApp passe par lui-même).
 *
 * Donne au compte administrateur un mot de passe provisoire, comme « Générer un mot de passe
 * provisoire » de la fiche d'un inscrit : affiché UNE fois dans le résultat de la commande, jamais
 * journalisé ; sessions fermées ; nouveau mot de passe exigé à la prochaine connexion
 * (EnsurePasswordIsChanged). Aucune question : utilisable depuis l'onglet « Commands » de Laravel Cloud.
 * Plusieurs administrateurs : la commande les liste (numéros masqués) et demande --id.
 */
class ResetAdminPassword extends Command
{
    public const PASSWORD_LENGTH = 12;

    protected $signature = 'app:reset-admin-password
        {--id= : Numéro du compte, seulement s\'il y a plusieurs administrateurs}';

    protected $description = 'Donne un mot de passe provisoire au compte administrateur (à changer à la prochaine connexion)';

    public function handle(): int
    {
        $admins = User::query()->where('role', Role::Admin)->orderBy('id')->get();

        if ($admins->isEmpty()) {
            $this->error('Aucun compte administrateur. Créez-en un avec php artisan app:create-admin --no-interaction.');

            return self::FAILURE;
        }

        $id = $this->option('id');
        $admin = filled($id) ? $admins->firstWhere('id', (int) $id) : ($admins->count() === 1 ? $admins->first() : null);

        if ($admin === null) {
            $this->error(filled($id)
                ? 'Aucun administrateur ne porte ce numéro de compte.'
                : 'Il y a plusieurs administrateurs : relancez avec --id= suivi du numéro du compte.');
            foreach ($admins as $candidate) {
                $this->line('  --id='.$candidate->id.' : '.$candidate->name.' ('.$this->mask($candidate->phone).')');
            }

            return self::FAILURE;
        }

        $password = TemporaryPassword::generate(self::PASSWORD_LENGTH);

        $admin->password = $password;
        $admin->must_change_password = true;
        $admin->setRememberToken(null);
        $admin->save();

        // Ferme les sessions ouvertes de ce compte (même chose que depuis la fiche d'un inscrit).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $admin->id)->delete();
        }

        // Seul endroit où le mot de passe apparaît : la sortie de la commande, jamais un journal.
        $this->info('Mot de passe provisoire donné au compte administrateur '.$this->mask($admin->phone).'.');
        $this->newLine();
        $this->line('  Mot de passe provisoire : '.$password);
        $this->newLine();
        $this->line('Connectez-vous avec votre numéro et ce mot de passe : le site vous demandera aussitôt d\'en choisir un nouveau.');
        $this->line('Ce mot de passe provisoire ne servira plus dès que vous aurez choisi le nouveau.');

        return self::SUCCESS;
    }

    private function mask(string $phone): string
    {
        return substr($phone, 0, 4).str_repeat('•', max(strlen($phone) - 6, 0)).substr($phone, -2);
    }
}
