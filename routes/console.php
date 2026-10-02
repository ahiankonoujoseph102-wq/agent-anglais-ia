<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| Sur Hostinger, une seule tâche cron (chaque minute) déclenche le tout :
|   * * * * * cd /chemin/vers/le/projet && php artisan schedule:run >> /dev/null 2>&1
|
| Ne jamais compter sur un processus permanent (pas de queue:work, pas de websockets).
|
*/

Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping();
