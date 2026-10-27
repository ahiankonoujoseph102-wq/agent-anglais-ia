<?php

// EN LOCAL : valeurs du prospectus lues dans la configuration du site (jamais écrites à la main) : nom, formule la
// moins chère, durée de l'évaluation gratuite, WhatsApp, téléphones et e-mail de l'équipe. Sortie JSON pour build.mjs.
use App\Support\Plan;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cheapest = collect(Plan::all())->sortBy('price')->first();

echo json_encode([
    'platform' => config('platform.name'),
    'teacher' => config('avatar.model.name'),
    'price' => $cheapest->price,
    'period_days' => $cheapest->durationDays,
    'assessment_minutes' => (int) config('platform.assessment.max_minutes'),
    'whatsapp' => (string) config('platform.contact.whatsapp'),
    'phones' => array_values((array) config('platform.contact.phones')),
    'email' => (string) config('platform.contact.email'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
