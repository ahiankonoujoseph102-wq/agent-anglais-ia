# CLAUDE.md — Teacher Joe (nom provisoire)

Ce fichier est le point de départ de chaque session de développement. Le lire en entier avant de toucher au code, et le mettre à jour quand une décision change.

## Le projet

Plateforme web qui enseigne l'anglais aux francophones d'Afrique grâce à un professeur IA.

Parcours de l'apprenant :

1. Il crée un compte avec son **nom** et son **numéro de téléphone** (+ mot de passe).
2. Il passe une **évaluation orale gratuite** avec le professeur IA : 5 minutes maximum, **une seule par compte**.
3. Le professeur lui annonce son **niveau CECRL** (A1, A2, B1, B2, C1, C2) et l'invite à s'abonner.
4. Il **paie par Mobile Money, manuellement** : il envoie l'argent depuis son téléphone sur le numéro affiché (Togo, Burkina Faso, Bénin), puis déclare son paiement (opérateur, numéro, référence, capture d'écran). **L'administrateur valide** et l'abonnement s'active. Aucune API de paiement.
5. Une fois le paiement validé, le professeur **construit avec lui son programme personnel**.
6. Chaque jour, il suit une **séance orale limitée dans le temps**. Le professeur annonce la durée au début, recadre l'apprenant qui sort du sujet, prévient 2 minutes avant la fin, résume et clôt la séance. **Le site coupe la séance automatiquement à la limite** (côté serveur, pas seulement côté navigateur).

Le professeur est un **personnage animé en 3D** dans le navigateur, dont la bouche suit la voix. La voix et l'intelligence viennent de l'**API Gemini** de Google.

## Contraintes non négociables

- **Laravel 13** (dernière stable) + **MySQL/MariaDB**.
- **Hébergement : Laravel Cloud** (décision de la phase 3, remplace Hostinger).
  - Rester simple : pas de serveur Node, pas de websockets côté serveur (Reverb), pas de worker de file d'attente. `QUEUE_CONNECTION=sync`.
  - Tâches planifiées par le **planificateur Laravel Cloud** (option « Scheduler » du cluster App) → `schedule:run` chaque minute ; chaque tâche avec `onOneServer()`.
  - **Système de fichiers éphémère** sur Laravel Cloud : tout fichier à conserver (captures de paiement) va sur un **bucket privé** (Laravel Cloud Object Storage, compatible S3), jamais sur le disque du serveur.
- **Paiement manuel uniquement** : aucune API ni aucun prestataire de paiement en ligne. Coordonnées de paiement **uniquement** dans `config/payment.php`. Au Togo : « Mixx by Yas » et « Flooz », jamais « T-Money ».
- **Interface entièrement en français**, **pensée d'abord pour le téléphone**, **légère en données** (pas de police web, pas de framework CSS, pas d'image inutile).
- **Aucune clé secrète dans le code** : tout passe par `.env` (lu via `config/*.php`, jamais `env()` ailleurs que dans `config/`).
- **Nom commercial, formules, prix, durées et coordonnées du centre** : uniquement dans `config/platform.php`. Jamais en dur dans le code ou les vues → `config('platform.name')`, `App\Support\Plan::all()`, `config('platform.contact')`, etc.

Formules de départ (dans `config/platform.php`) :

| Clé | Nom | Minutes/jour | Prix | Validité |
|---|---|---|---|---|
| `essentiel` | Essentiel | 15 | 5 000 FCFA | 30 jours (par mois) |
| `intensif` | Intensif | 30 | 9 000 FCFA | 30 jours (par mois) |
| `semaine` | Semaine | 15 | 1 500 FCFA | 7 jours (par semaine) |

## Feuille de route

- **Phase 1 — Fondations (faite)** : projet Laravel, base de données, inscription/connexion par téléphone, accueil public, espace apprenant (tableau de bord), espace admin (liste des inscrits), tests.
- **Phase 2 — Conversation vocale (faite)** : évaluation orale gratuite et séance du jour avec l'API Gemini Live, jetons éphémères, limite de temps imposée par le serveur, consignes du professeur dans `config/teacher.php`, visage SVG animé, mesures de consommation dans l'admin.
- **Phase 3 — Paiement manuel et comptes (faite)** : paiement Mobile Money déclaré par l'apprenant (capture privée), validation/refus par l'administrateur, activation et renouvellement de l'abonnement, rappel 3 jours avant la fin, mot de passe oublié via WhatsApp + mot de passe provisoire, coordonnées du centre (pied de page, page Contact).
- **Phase 4** : construction du programme, séances quotidiennes chronométrées.
- **Phase 5** : personnage 3D animé avec synchronisation labiale.

(L'ordre 2/3/4/5 est indicatif ; voir le compte rendu de chaque phase.)

## Architecture

```
app/
  Console/Commands/      app:create-admin, subscriptions:expire, live:close-stale
  Enums/                 Level (A1…C2), Role, *Status — valeurs stockées en anglais, libellés FR via label()
  Http/Controllers/      HomeController, ContactController, DashboardController, Auth/* (dont PasswordController),
                         Admin/* (PaymentController, UserController, UsageController, AssessmentController)
  Http/Controllers/Live/ AssessmentController (évaluation), LessonController (séance du jour)
  Http/Controllers/Payment/ PaymentController (parcours de paiement de l'apprenant)
  Http/Middleware/       EnsureUserIsAdmin (alias « admin »), EnsurePasswordIsChanged (groupe web)
  Http/Requests/Auth/    RegisterRequest, LoginRequest (normalisation du téléphone + limitation des essais)
  Http/Requests/Payment/ StorePaymentRequest ; Http/Requests/Admin/ RejectPaymentRequest
  Http/Requests/Live/    ConnectRequest, SignalRequest, AssessmentResultRequest, LessonReportRequest
  Models/                User, Assessment, Program, Subscription, Payment, Lesson
  Models/Concerns/       IsLiveConversation (règles de temps communes à Assessment et Lesson)
  Support/               PhoneNumber (normalisation E.164), Plan (formules depuis la config), Money, Duration,
                         PaymentCountry / PaymentOperator (config/payment.php), WhatsApp (liens wa.me), TemporaryPassword
  Support/Gemini/        LiveClient (jeton éphémère), LiveConnection, TeacherSetup (consignes + config Live), UsageCost
config/platform.php      TOUS les réglages commerciaux (+ durée de l'évaluation, préavis de fin, signaux, coordonnées du centre)
config/payment.php       pays, opérateurs, numéros et titulaires Mobile Money ; WhatsApp ; captures ; délai du rappel
config/teacher.php       consignes du professeur (modifiables sans toucher au code)
config/services.php      gemini : clé, modèle Live, version d'API, voix, durée des jetons, tarifs pour l'estimation
public/js/               live-session.js (conversation vocale), pcm-capture-worklet.js (micro → PCM 16 kHz), copy.js (bouton « Copier »)
lang/fr/, lang/fr.json   traductions (validation, erreurs HTTP, pagination)
public/css/app.css       feuille de style unique, écrite à la main, sans compilation
resources/views/
  components/layout.blade.php   gabarit commun (<x-layout>)
  components/input.blade.php    champ de formulaire avec erreurs (<x-input>)
```

### Base de données

| Table | Rôle | Points clés |
|---|---|---|
| `users` | comptes | `phone` unique au format `+22890123456`, `level` (CECRL, nullable), `role` (`learner`/`admin`), `must_change_password` (mot de passe provisoire). Pas d'e-mail. |
| `assessments` | évaluation gratuite | `user_id` **unique** → une seule évaluation par compte, garantie par la base. `allowed_seconds`, `used_seconds`, `level`, `feedback` + colonnes de suivi (ci-dessous). |
| `programs` | programme personnel | `start_level`, `target_level`, `goals`, `content` (JSON), `is_active`. |
| `subscriptions` | abonnements | `plan` (clé de config) + **copie** de `plan_name`, `daily_minutes`, `price`, `currency` au moment de l'achat ; `starts_at`, `ends_at`, `status`. Créé à la validation d'un paiement. |
| `payments` | paiements Mobile Money déclarés | copie de la formule (`plan`, `plan_name`, `daily_minutes`, `duration_days`, `amount`) ; `country`, `operator`, `operator_name`, `recipient_number` ; `payer_phone`, `reference`, `proof_path` (capture, disque privé) ; `status` (`pending`/`validated`/`rejected`), `rejection_reason`, `reviewed_by`, `reviewed_at`, `subscription_id`. |
| `lessons` | séances quotidiennes | `date`, `allowed_seconds`, `used_seconds` (durée consommée), `summary`, `homework`, `status` + colonnes de suivi. Nommée `lessons` car `sessions` est réservée par Laravel. |

Colonnes de suivi (assessments et lessons) : `last_seen_at` (dernier signal du navigateur), `token_expires_at` (fin du dernier jeton), `connections` (jetons délivrés), `input_tokens`, `output_tokens` (rapportés par Gemini).

Temps restant du jour = `subscription.daily_minutes × 60 − Σ lessons.used_seconds du jour` (`User::remainingSecondsToday()`), jamais négatif. Le « jour » suit `APP_TIMEZONE` (par défaut `Africa/Lome`).

### Paiement manuel (Mobile Money)

- Parcours apprenant en 4 étapes : `/abonnement` (formule) → `/abonnement/{plan}?pays=TG` (opérateurs, numéro, titulaire, montant exact, bouton « Copier le numéro » qui copie le numéro local) → formulaire (opérateur, numéro payeur, référence, capture) → `/paiements/{id}` (« Paiement en attente de validation » + bouton « Prévenir sur WhatsApp » avec message pré-rédigé).
- Règles garanties **par la base** (index uniques) :
  - `reference_key` = référence normalisée (majuscules, sans espaces ni ponctuation) : une même référence ne peut pas être envoyée deux fois. Elle est remise à `null` au refus, pour permettre un renvoi corrigé.
  - `pending_user_id` = `user_id` tant que le paiement est en attente, `null` ensuite (géré par `Payment::booted`) : un seul paiement en attente par apprenant.
- Captures : images JPG/PNG/WebP, 5 Mo max, sur le disque `config('payment.proofs.disk')` (privé), servies uniquement par la route admin `admin.payments.proof` (jamais d'URL publique).
- Validation (`Payment::approve`) : crée l'abonnement actif ; s'il en reste un en cours, le nouveau commence à sa fin (`starts_at` = fin la plus lointaine des abonnements actifs). Refus (`Payment::reject`) : motif obligatoire, affiché à l'apprenant.
- Renouvellement : rappel dans l'espace apprenant `payment.renewal_reminder_days` (3) jours avant la fin, sauf si le suivant est déjà payé. À l'expiration, `/seance` redirige vers `/abonnement` ; l'évaluation et le niveau restent visibles.

### Mot de passe oublié (sans SMS ni e-mail)

- `/mot-de-passe-oublie` : explications + bouton WhatsApp avec un message contenant le numéro du compte.
- Fiche inscrit (`/admin/inscrits/{id}`) : « Générer un mot de passe provisoire » → affiché **une seule fois** (donnée flash), sessions de l'apprenant fermées, `must_change_password = true`.
- Le middleware `EnsurePasswordIsChanged` redirige alors vers `/mot-de-passe/nouveau` avant tout le reste (403 JSON pour les appels JavaScript).

### Conversation vocale (Gemini Live)

- Le navigateur se connecte **directement** à Google en WebSocket (`BidiGenerateContentConstrained`) avec un **jeton éphémère** à usage unique demandé par Laravel (`POST /{version}/auth_tokens`). La clé reste dans `.env`. Le jeton **fige** la configuration (modèle, consignes, fonctions) : le navigateur ne peut pas la changer.
- Audio : micro → PCM 16 bits mono 16 kHz (`audio/pcm;rate=16000`) ; voix reçue en PCM 16 bits 24 kHz. Sous-titres via `inputAudioTranscription` / `outputAudioTranscription`.
- **Temps imposé par le serveur** : une conversation se déroule d'un seul tenant ; le temps consommé = temps écoulé depuis le début, plafonné à `allowed_seconds`. Chaque jeton expire au plus tard à la fin autorisée (et au plus après `GEMINI_TOKEN_MINUTES`) : Google coupe alors la connexion. Le navigateur renouvelle le jeton avant expiration (et sur `goAway`) en reprenant la conversation (`sessionResumption`). Signal toutes les 15 s (`/signal`) ; sans signal, la conversation est close par `live:close-stale` (cron chaque minute), **décomptée jusqu'à l'expiration du dernier jeton**. Une seule séance ouverte à la fois ; refus de toute connexion quand le temps du jour est épuisé.
- Le professeur appelle des fonctions : `record_assessment_result(level, feedback)` (évaluation → `assessments` + `users.level`, une seule fois) et `end_lesson(summary, homework)`. Le navigateur les relaie à Laravel puis répond à Gemini.
- Les indications « début », « reprise » et « il reste N minutes » sont envoyées par le navigateur au bon moment (textes dans `config/teacher.php`, clé `cues`).

### Routes (URL en français)

| URL | Nom | Accès |
|---|---|---|
| `/` | `home` | public |
| `/contact` | `contact` | public : coordonnées du centre |
| `/mot-de-passe-oublie` | `password.forgot` | invités |
| `/mot-de-passe/nouveau` (GET, PUT) | `password.change`, `password.update` | connectés (après mot de passe provisoire) |
| `/inscription` | `register` | invités |
| `/connexion` | `login` | invités (5 essais puis blocage temporaire) |
| `/deconnexion` (POST) | `logout` | connectés |
| `/espace` | `dashboard` | connectés |
| `/abonnement`, `/abonnement/{plan}` (GET, POST) | `payment.plans`, `payment.create`, `payment.store` | connectés |
| `/paiements/{payment}` | `payment.show` | le propriétaire du paiement |
| `/evaluation` (+ POST `/connexion`, `/signal`, `/resultat`, `/fin`) | `assessment.*` | connectés (une seule évaluation) |
| `/seance` (+ POST `/connexion`, `/signal`, `/bilan`, `/fin`) | `lesson.*` | connectés avec abonnement actif |
| `/admin` → `/admin/paiements` | `admin.payments.pending` | administrateurs (403 sinon) : paiements en attente, Valider / Refuser |
| `/admin/paiements/historique` | `admin.payments.index` | filtres statut / pays / période, total encaissé du mois |
| `/admin/paiements/{payment}/capture` | `admin.payments.proof` | capture d'écran (stockage privé) |
| `/admin/inscrits`, `/admin/inscrits/{user}` | `admin.users.index`, `admin.users.show` | liste et fiche (mot de passe provisoire) |
| `/admin/consommation` | `admin.usage.index` | administrateurs : durée réelle, jetons, coût estimé par apprenant |
| DELETE `/admin/inscrits/{user}/evaluation` | `admin.assessments.destroy` | administrateurs : permettre de repasser l'évaluation |

Il n'existe **aucun moyen de devenir administrateur depuis le site** : `php artisan app:create-admin`.

## Conventions de code

- **Code en anglais** (classes, méthodes, tables, colonnes, valeurs d'énumération), **tout ce que voit l'utilisateur en français** (vues, messages de validation, messages flash, libellés d'énumération via `label()`). Les commentaires sont en français.
- Style Laravel standard, vérifié par **Pint** (`vendor/bin/pint`). La CI échoue si le style n'est pas respecté.
- Contrôleurs fins ; validation dans des `FormRequest` ; logique métier réutilisable dans les modèles ou `app/Support`.
- Attributs Laravel 13 sur les modèles (`#[Fillable]`, `#[Hidden]`). `role` et `level` **ne sont jamais fillable**.
- Statuts et niveaux = **enums PHP** castés dans les modèles ; ne pas comparer à des chaînes brutes.
- Les montants sont des **entiers en FCFA** (pas de centimes, pas de float).
- Téléphones : toujours passer par `App\Support\PhoneNumber::normalize()` avant de stocker ou chercher.
- Mode strict Eloquent activé hors production (`Model::shouldBeStrict`) : charger les relations avec `with()`.
- Pas de dépendance front à compiler : `public/js/*.js` sont des modules ES écrits à la main (pas de SDK Google côté navigateur, le protocole WebSocket est utilisé directement). Si un jour une bibliothèque est nécessaire (personnage 3D), servir des modules ES depuis `public/` ou compiler **en local** et committer le résultat ; jamais de build sur le serveur.
- Appels à Gemini : uniquement via `Http` (facade) pour pouvoir les simuler dans les tests (`Http::fake`). Aucun test n'appelle la vraie API.
- Vues : composants Blade anonymes (`<x-layout>`, `<x-input>`), CSS dans `public/css/app.css`, mobile d'abord (les règles `@media (min-width: 640px)` élargissent).
- Toute nouvelle fonctionnalité arrive avec ses tests (PHPUnit, `tests/Feature` et `tests/Unit`).

## Commandes utiles

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # données de démo (admin +22890000000 / password)
php artisan serve                 # http://localhost:8000
php artisan test                  # SQLite en mémoire (rapide)
DB_CONNECTION=mysql DB_DATABASE=… php artisan test   # contre MySQL, comme la CI
vendor/bin/pint                   # formatage
php artisan app:create-admin      # créer / promouvoir un administrateur
php artisan subscriptions:expire  # aussi lancée chaque heure par le planificateur
php artisan live:close-stale      # clôt les conversations finies/abandonnées (chaque minute par le planificateur)
```

La CI (`.github/workflows/tests.yml`) lance Pint et toute la suite de tests contre **MySQL 8**.

## Déploiement sur Laravel Cloud (résumé)

1. Sur cloud.laravel.com, créer une application à partir du dépôt GitHub (branche `main`).
2. Dans l'environnement, attacher les ressources :
   - une base **MySQL** (les variables `DB_*` sont injectées automatiquement) ;
   - un **bucket Object Storage privé**, défini comme **disque par défaut** : il reçoit les captures de paiement (le disque du serveur est effacé à chaque déploiement).
3. Variables d'environnement : `APP_KEY` (générée en local avec `php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_TIMEZONE=Africa/Lome`, `APP_LOCALE=fr`, `GEMINI_API_KEY`, et les autres réglages de `.env.example` si besoin. Ne jamais committer ces valeurs.
4. Commande de build : `composer install --no-dev --optimize-autoloader` (aucune compilation front). Commande de déploiement : `php artisan migrate --force`.
5. Activer **Scheduler** sur le cluster App (lance `schedule:run` chaque minute), puis redéployer.
6. Après le premier déploiement, créer l'administrateur depuis la console Laravel Cloud : `php artisan app:create-admin`.
7. Le site est servi en **HTTPS** (obligatoire pour le micro).
