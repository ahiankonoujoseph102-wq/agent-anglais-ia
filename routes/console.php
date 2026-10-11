<?php

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
