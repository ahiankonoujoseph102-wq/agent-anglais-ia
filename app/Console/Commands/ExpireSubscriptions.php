<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Passe à « expirée » les abonnements dont la date de fin est dépassée et arrête les accès provisoires de plus de 24 heures';

    public function handle(): int
    {
        $count = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        // Accès provisoire sans décision de l'administrateur : il s'arrête, le paiement reste
        // en attente de vérification (l'accès est de toute façon refusé dès l'heure passée).
        $suspended = Subscription::query()
            ->where('status', SubscriptionStatus::Provisional)
            ->where('provisional_until', '<=', now())
            ->update(['status' => SubscriptionStatus::Suspended]);

        $this->info($count.' abonnement(s) expiré(s), '.$suspended.' accès provisoire(s) arrêté(s).');

        return self::SUCCESS;
    }
}
