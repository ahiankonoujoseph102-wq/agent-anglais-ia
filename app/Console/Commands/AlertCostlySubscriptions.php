<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use App\Support\Live\CostWatch;
use Illuminate\Console\Command;

class AlertCostlySubscriptions extends Command
{
    protected $signature = 'usage:alert-costs';

    protected $description = 'Prévient l\'administrateur quand les séances d\'un abonné dépassent le seuil de coût (une seule alerte par formule)';

    public function handle(AdminAlerts $alerts): int
    {
        $sent = 0;

        foreach (CostWatch::current()->where('over', true) as $row) {
            $subscription = $row['subscription'];

            // Marqué avant l'envoi : jamais deux alertes pour la même formule, même si l'envoi échoue.
            if ($subscription->cost_alert_sent_at !== null
                || ! Subscription::query()->whereKey($subscription->id)->whereNull('cost_alert_sent_at')->update(['cost_alert_sent_at' => now()])) {
                continue;
            }

            $alerts->send(AlertBuilder::costly($row));
            $sent++;
        }

        $this->info($sent.' alerte(s) de coût.');

        return self::SUCCESS;
    }
}
