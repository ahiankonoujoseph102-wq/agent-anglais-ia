<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserOrigin;
use App\Support\Origins;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Provenance » : d'où viennent les inscrits (canal, page d'arrivée), puis évaluation passée et abonnement.
 */
class OriginController extends Controller
{
    /** Périodes proposées, en jours (null : depuis toujours). */
    public const PERIODS = ['7' => 7, '30' => 30, '90' => 90, 'tout' => null];

    public function index(Request $request): View
    {
        $period = array_key_exists((string) $request->query('jours'), self::PERIODS) ? (string) $request->query('jours') : '30';
        $days = self::PERIODS[$period];

        return view('admin.origins.index', [
            'period' => $period,
            'days' => $days,
            'stats' => Origins::stats($days ? now()->subDays($days) : null),
            'since' => UserOrigin::query()->min('created_at'),
        ]);
    }
}
