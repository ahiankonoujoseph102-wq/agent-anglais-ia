<?php

/*
|--------------------------------------------------------------------------
| Création de l'administrateur (php artisan app:create-admin)
|--------------------------------------------------------------------------
|
| En terminal, la commande DEMANDE le numéro et le mot de passe : rien n'est à régler ici.
|
| Sur Laravel Cloud, l'onglet « Commands » ne permet aucune question (commandes
| non interactives). On définit alors TEMPORAIREMENT ces deux variables dans l'environnement,
| on lance la commande, puis on les SUPPRIME et on redéploie (voir docs/deploiement-laravel-cloud.md).
| Elles ne servent à rien d'autre : ne jamais les laisser en place, ni les écrire dans le code.
|
*/

return [

    'bootstrap' => [
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Lien de récupération de l'administrateur (php artisan app:send-admin-recovery) :
    | lien à usage unique, valable quelques minutes, envoyé SEULEMENT à cette adresse (jamais affiché).
    */
    'recovery' => [
        'email' => env('ADMIN_RECOVERY_EMAIL', 'amitalk62@gmail.com'),
        'minutes' => 30,
    ],

];
