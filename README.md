# agent-anglais-ia — Teacher Joe

Plateforme web d'apprentissage de l'anglais avec un professeur IA animé : évaluation gratuite, programme personnel, séances quotidiennes limitées dans le temps, abonnement par mobile money. Laravel, pensée pour le téléphone et pour l'Afrique francophone.

## Démarrage rapide

Prérequis : PHP 8.3+, Composer, MySQL ou MariaDB.

```bash
composer install
cp .env.example .env
php artisan key:generate
# renseigner DB_DATABASE, DB_USERNAME, DB_PASSWORD dans .env
php artisan migrate --seed
php artisan serve
```

Comptes de démonstration créés par `--seed` (mot de passe `password`) :

- administrateur : `90 00 00 00`
- apprenant abonné (niveau A2, formule Essentiel) : `90 00 00 01`

## Tests

```bash
php artisan test
```

## Réglages

Le nom commercial, les formules, les prix et les durées se règlent dans `config/platform.php`. Les clés secrètes vont uniquement dans `.env`.

Toute la documentation pour les développeurs (architecture, conventions, déploiement Hostinger) est dans [`CLAUDE.md`](CLAUDE.md).
