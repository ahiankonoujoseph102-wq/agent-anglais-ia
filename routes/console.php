<?php

use App\Console\Commands\CheckDeployment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| Sur Laravel Cloud, activer « Scheduler » sur le cluster App de l'environnement :
| « php artisan schedule:run » est alors lancé chaque minute. onOneServer()
| évite qu'une tâche tourne en double quand plusieurs serveurs (répliques)
| sont actifs.
|
| En local ou sur un autre hébergeur, une seule tâche cron suffit :
|   * * * * * cd /chemin/vers/le/projet && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping()->onOneServer();

// Clôture les évaluations et séances finies ou abandonnées (navigateur fermé).
Schedule::command('live:close-stale')->everyMinute()->withoutOverlapping()->onOneServer();

// Témoin du planificateur : « php artisan app:check-deployment » vérifie qu'il a tourné il y a moins de trois minutes.
Schedule::call(fn () => Cache::put(CheckDeployment::HEARTBEAT_KEY, now()->timestamp, now()->addHour()))
    ->name('scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Messages vocaux communs (bienvenue, résultat de l'évaluation) : ce qui manque encore est préparé, sans dépasser
// la limite de Google ; aucune demande quand tout est prêt. Aussi lancée à chaque déploiement.
Schedule::command('app:prepare-voice --budget=50')->hourly()->withoutOverlapping()->onOneServer();

// Texte des conversations : effacé après platform.transcripts.keep_days jours (90), chaque nuit.
Schedule::command('transcripts:purge')->dailyAt('03:10')->withoutOverlapping()->onOneServer();

// Alerte de l'administrateur : un accès provisoire se termine dans moins de deux heures sans validation (une seule par paiement).
Schedule::command('payments:alert-expiring')->everyTenMinutes()->withoutOverlapping()->onOneServer();

// Documents de révision des séances « Mes cours » : quelques séances par passage, jamais deux passages en même temps
// (verrou libéré au plus tard après 10 minutes si un passage s'arrête brutalement).
Schedule::command('courses:prepare-reviews')->everyMinute()->withoutOverlapping(10)->onOneServer();

// Invitations à refaire l'évaluation gratuite (après app:reset-learners --notify) : par lots, dans la marge
// d'e-mails du jour partagée avec les documents de révision (jamais les e-mails gardés pour les alertes).
Schedule::command('learners:send-invitations')->hourly()->withoutOverlapping()->onOneServer();
