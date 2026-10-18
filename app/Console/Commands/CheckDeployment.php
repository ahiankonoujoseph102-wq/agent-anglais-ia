<?php

namespace App\Console\Commands;

use App\Support\Live\LiveProviders;
use App\Support\PaymentCountry;
use App\Support\PrivateStorage;
use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Contrôle d'une mise en ligne (Laravel Cloud) : réglages de sécurité, base de données, disque privé,
 * planificateur. Se lance depuis l'onglet « Commands » : « php artisan app:check-deployment ».
 * N'affiche jamais une valeur secrète (clé, mot de passe, numéro) : seulement « oui » ou « non ».
 */
class CheckDeployment extends Command
{
    /**
     * Clé de cache écrite chaque minute par le planificateur (voir routes/console.php).
     */
    public const HEARTBEAT_KEY = 'platform:scheduler-heartbeat';

    protected $signature = 'app:check-deployment';

    protected $description = 'Vérifie la mise en ligne : sécurité, base de données, disque privé, planificateur';

    private int $failures = 0;

    public function handle(): int
    {
        $this->line('Contrôle de la mise en ligne de '.config('platform.name'));

        $production = app()->isProduction();

        $this->section('Sécurité');
        $this->check('Environnement « production »', $production, 'APP_ENV devrait valoir production.');
        $this->check('Mode débogage coupé', ! config('app.debug'), 'APP_DEBUG doit valoir false.');
        $this->check('HTTPS obligatoire', (bool) config('app.force_https'), 'APP_FORCE_HTTPS ne doit pas être vide ni false.');
        $this->check('Cookies de session sécurisés', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE ne doit pas valoir false.');
        $this->check('Adresse du site en https://', Str::startsWith((string) config('app.url'), 'https://'), 'APP_URL doit commencer par https://.');
        $this->check('Fuseau horaire Africa/Lome', config('app.timezone') === 'Africa/Lome', 'APP_TIMEZONE doit valoir Africa/Lome.');
        $this->check('Clé de chiffrement présente', filled(config('app.key')), 'APP_KEY est vide.');
        $this->check('Sessions en base de données', config('session.driver') === 'database', 'SESSION_DRIVER doit valoir database (le disque du serveur est effacé à chaque déploiement).');
        $this->check('Cache en base de données', config('cache.default') === 'database', 'CACHE_STORE doit valoir database.');

        $this->section('Base de données');
        $this->checkDatabase();

        $this->section('Fichiers privés (captures de paiement, attestations)');
        $this->checkPrivateDisk($production);

        $this->section('Planificateur');
        $this->checkScheduler();

        // Valeurs par défaut de la configuration, remplacées par celles saisies dans les Réglages (facultatifs).
        $this->section('Moyens de paiement et coordonnées (configuration et Réglages)');
        $operators = array_sum(array_map(fn (PaymentCountry $country) => count($country->operators), PaymentCountry::available()));
        $this->check("Opérateurs Mobile Money proposés : {$operators}", $operators > 0, 'Aucun numéro de réception : ni valeur par défaut dans config/payment.php, ni numéro saisi dans Administration, « Réglages ». Les apprenants ne peuvent pas payer.');
        $this->check('Numéro WhatsApp de l\'équipe', SiteSettings::whatsapp() !== null, 'Aucun numéro WhatsApp : ajoutez-le dans Administration, « Réglages » (boutons « Prévenir sur WhatsApp », mot de passe oublié).');
        $custom = count(array_filter(array_keys(self::settingKeys()), fn (string $key) => SiteSettings::saved($key) !== null));
        $this->line("    Valeurs saisies dans les Réglages : {$custom} (les autres : valeurs par défaut)");

        // Référencement : informations seulement, jamais un échec (aucune valeur n'est obligatoire).
        $this->section('Référencement');
        $this->line('    Adresse du site : '.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'absente'));
        $this->line('    Balise Google Search Console : '.(filled(config('services.google.site_verification')) ? 'présente' : 'absente (GOOGLE_SITE_VERIFICATION)'));
        $redirects = (array) config('app.redirect_from_hosts');
        $this->line('    Anciennes adresses redirigées en 301 : '.($redirects === [] ? 'aucune' : implode(', ', $redirects)));
        $this->line('    Groupe WhatsApp : '.(filled(config('platform.contact.whatsapp_group')) ? 'lien présent' : 'à compléter (WHATSAPP_GROUP_URL ; sinon le numéro WhatsApp)'));
        $this->line('    Présentation du fondateur (page « À propos ») : '.(filled(config('platform.founder.bio')) ? 'présente' : 'à compléter (config/platform.php, founder.bio)'));

        $this->section('Professeur IA');
        $this->check('Clé Gemini présente', filled(config('services.gemini.key')), 'GEMINI_API_KEY est vide : les séances vocales ne fonctionneront pas.');
        // Séances en direct : fournisseurs utilisables dans l'ordre réglé (jamais la clé, seulement sa présence).
        $live = array_map(fn (string $name) => LiveProviders::label($name), LiveProviders::available());
        $this->check('Séances en direct : '.($live === [] ? 'aucun fournisseur' : implode(', puis ', $live)), $live !== [], 'Aucun fournisseur de séance en direct n\'a de clé (LIVE_PROVIDERS, GEMINI_API_KEY, OPENAI_API_KEY).');
        $this->line('    Secours OpenAI : '.(filled(config('services.openai.key')) ? 'clé présente' : 'clé absente (pas de secours)'));

        $this->newLine();

        if ($this->failures > 0) {
            $this->error($this->failures.' point(s) à corriger.');

            return self::FAILURE;
        }

        $this->info('Tout est en ordre.');

        return self::SUCCESS;
    }

    /**
     * Clés des Réglages (numéros, titulaires, coordonnées).
     *
     * @return array<string, true>
     */
    private static function settingKeys(): array
    {
        $keys = array_fill_keys(SiteSettings::CONTACT_KEYS, true);

        foreach (PaymentCountry::all() as $country) {
            foreach ($country->operators as $operator) {
                $keys[SiteSettings::operatorNumberKey($country->code, $operator->key)] = true;
                $keys[SiteSettings::operatorHolderKey($country->code, $operator->key)] = true;
            }
        }

        return $keys;
    }

    private function checkDatabase(): void
    {
        try {
            $driver = DB::connection()->getDriverName();
            DB::select('select 1');
            $migrated = DB::table('migrations')->count();
            $this->check("Connexion à la base ({$driver}), {$migrated} migrations appliquées", $driver === 'mysql' && $migrated > 0, 'La base doit être MySQL et migrée (php artisan migrate --force).');
        } catch (Throwable) {
            $this->check('Connexion à la base', false, 'Connexion impossible : la base MySQL est-elle attachée à l\'environnement ?');
        }
    }

    private function checkPrivateDisk(bool $production): void
    {
        try {
            $name = PrivateStorage::name();
            $disk = PrivateStorage::disk();
            $driver = config("filesystems.disks.{$name}.driver");

            $this->check("Disque privé « {$name} » ({$driver})", ! $production || $driver === 's3', 'En production, le disque doit être le bucket (driver s3) : le disque du serveur est effacé à chaque déploiement.');

            $path = 'controle/'.Str::random(12).'.txt';
            $disk->put($path, 'ok');
            $readable = $disk->get($path) === 'ok';
            $disk->delete($path);

            $this->check('Écriture, lecture et suppression d\'un fichier test', $readable && ! $disk->exists($path), 'Le disque privé n\'est pas accessible en écriture.');

            $this->line('    Captures de paiement enregistrées : '.count($disk->files(config('payment.proofs.directory'), true)));
            $this->line('    Attestations enregistrées : '.count($disk->files('attestations')));
            $this->line('    Fichiers de « Mes cours » : '.count($disk->allFiles(trim((string) config('platform.courses.directory'), '/'))));

            // Nom du bucket (pas un secret) : à comparer à celui prévu, « talkami-fichiers ».
            if (filled($bucket = config("filesystems.disks.{$name}.bucket"))) {
                $this->line('    Bucket utilisé : '.$bucket);
            }
        } catch (Throwable $e) {
            $this->check('Disque privé', false, 'Erreur : '.class_basename($e).'. Le bucket est-il attaché comme disque par défaut ?');
        }
    }

    private function checkScheduler(): void
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        $age = $last ? now()->timestamp - (int) $last : null;

        $this->check(
            $age === null ? 'Le planificateur n\'a pas encore tourné' : "Dernier passage du planificateur il y a {$age} secondes",
            $age !== null && $age <= 180,
            'Activez « Scheduler » sur le cluster App, redéployez, puis attendez deux minutes et relancez cette commande.',
        );
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function check(string $label, bool $ok, string $advice): void
    {
        if ($ok) {
            $this->line("  <info>OK</info>   {$label}");

            return;
        }

        $this->failures++;
        $this->line("  <error> À CORRIGER </error> {$label}");
        $this->line("         {$advice}");
    }
}
