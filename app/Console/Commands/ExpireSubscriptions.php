<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Passe à « expirée » les abonnements dont la date de fin est dépassée';

    public function handle(): int
    {
        $count = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        $this->info($count.' abonnement(s) expiré(s).');

        return self::SUCCESS;
    }
}
