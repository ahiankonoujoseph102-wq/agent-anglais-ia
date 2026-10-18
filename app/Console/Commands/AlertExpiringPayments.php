<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertBuilder;
use Illuminate\Console\Command;

class AlertExpiringPayments extends Command
{
    protected $signature = 'payments:alert-expiring';

    protected $description = 'Prévient l\'administrateur quand un accès provisoire va se terminer sans validation (une seule alerte par paiement)';

    public function handle(AdminAlerts $alerts): int
    {
        $limit = now()->addHours((int) config('alerts.expiry_warning_hours'));

        $payments = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNull('expiry_alert_sent_at')
            ->whereHas('subscription', fn ($query) => $query
                ->where('status', SubscriptionStatus::Provisional)
                ->where('provisional_until', '>', now())
                ->where('provisional_until', '<=', $limit))
            ->with(['user', 'subscription'])
            ->get();

        foreach ($payments as $payment) {
            // Marqué avant l'envoi : même si l'envoi échoue, il n'y aura jamais deux alertes pour ce paiement.
            if (! Payment::query()->whereKey($payment->id)->whereNull('expiry_alert_sent_at')->update(['expiry_alert_sent_at' => now()])) {
                continue;
            }

            $alerts->send(AlertBuilder::expiring($payment));
        }

        $this->info($payments->count().' alerte(s) d\'accès provisoire.');

        return self::SUCCESS;
    }
}
