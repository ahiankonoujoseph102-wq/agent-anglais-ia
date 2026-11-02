<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearnerMail;
use App\Models\User;
use App\Support\Reminders\FollowUps;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Relances » : les inscrits qui n'ont pas encore de formule payée, par étape (sans évaluation, évaluation sans
 * essai, essai en cours, essai terminé), l'état de leurs e-mails de relance, et un bouton WhatsApp avec le message
 * déjà écrit (une seule relance WhatsApp par étape est notée, pour ne jamais insister).
 */
class FollowUpController extends Controller
{
    private const LIMIT = 200;

    public function index(Request $request): View
    {
        $filter = in_array($request->query('etape'), array_keys(FollowUps::STAGE_LABELS), true) ? $request->query('etape') : null;

        $rows = FollowUps::prospects()
            ->with(['learnerMails', 'activeSubscription'])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (User $user) {
                $stage = FollowUps::stage($user);
                $mails = $user->learnerMails->whereIn('campaign', LearnerMail::CAMPAIGNS)->sortByDesc('id');

                return [
                    'user' => $user,
                    'stage' => $stage,
                    'mail' => $mails->first(),
                    'whatsapp' => $user->learnerMails->firstWhere('campaign', 'wa_'.$stage),
                ];
            });

        $counts = $rows->countBy('stage');

        return view('admin.follow-ups.index', [
            'rows' => $filter ? $rows->where('stage', $filter)->values() : $rows,
            'counts' => $counts,
            'filter' => $filter,
            'labels' => FollowUps::STAGE_LABELS,
            'limit' => self::LIMIT,
            'sentToday' => FollowUps::sentToday(),
            'pending' => LearnerMail::query()->where('status', LearnerMail::PENDING)->count(),
            'enabled' => FollowUps::enabled(),
            'salesPending' => LearnerMail::query()->where('status', LearnerMail::PENDING)->whereIn('campaign', LearnerMail::SALES)->count(),
            'salesToday' => FollowUps::salesSentToday(),
            'lapsedSent' => LearnerMail::query()->whereIn('campaign', LearnerMail::LAPSED)->where('status', LearnerMail::SENT)->where('sent_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    /** Ouvre WhatsApp vers l'inscrit avec le message de son étape, et note la relance (une par étape). */
    public function whatsapp(User $user): RedirectResponse
    {
        abort_unless(FollowUps::prospects()->whereKey($user->id)->exists(), 404);

        $stage = FollowUps::stage($user);
        $user->learnerMails()->updateOrCreate(['campaign' => 'wa_'.$stage], ['status' => LearnerMail::SENT, 'sent_at' => now()]);

        return redirect()->away(WhatsApp::url($user->phone, FollowUps::whatsappMessage($user)));
    }
}
