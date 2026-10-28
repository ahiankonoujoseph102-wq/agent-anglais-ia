<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Subscribers;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Abonnés payants » : les abonnés d'un coup d'œil (chiffres, liste, relance WhatsApp), payants séparés des
 * ambassadeurs et des accès offerts.
 */
class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('statut'), Subscribers::FILTERS) ? (string) $request->query('statut') : 'actifs';
        $sort = array_key_exists((string) $request->query('tri'), Subscribers::SORTS) ? (string) $request->query('tri') : 'fin';
        $totals = Subscribers::totals();

        return view('admin.subscribers.index', [
            'filter' => $filter,
            'sort' => $sort,
            'groups' => Subscribers::list($filter, $sort),
            'totals' => $totals,
            'monthTotal' => Money::format($totals['month_total']),
            'monthLabel' => now()->translatedFormat('F Y'),
            'reminderDays' => (int) config('payment.renewal_reminder_days'),
        ]);
    }
}
