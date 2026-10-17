<?php

/*
|--------------------------------------------------------------------------
| Proxys de confiance
|--------------------------------------------------------------------------
|
| Derrière le réseau de Laravel Cloud, la requête arrive par un proxy qui indique
| le vrai protocole (https) et l'adresse du visiteur dans les en-têtes X-Forwarded-*.
| Laravel ne les croit que venant d'un proxy de confiance.
|
| Par défaut, en production : tous les proxys sont crus (« * »). Le serveur de Laravel Cloud n'est
| joignable que par son réseau, et sans cela Laravel croirait la requête en http : redirections sans fin
| et erreurs « page expirée » (419) à chaque envoi de formulaire. Hors production : aucun proxy n'est cru.
| Autre hébergeur : TRUSTED_PROXIES="*" (tous) ou une liste d'adresses séparées par des virgules.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES', env('APP_ENV', 'production') === 'production' ? '*' : null),

];
