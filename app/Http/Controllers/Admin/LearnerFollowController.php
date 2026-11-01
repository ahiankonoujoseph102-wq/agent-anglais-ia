<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Admin\LearnerProgress;
use App\Support\Admin\ViewAs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suivre chaque apprenant de près : « Suivi des élèves » (qui a fait sa séance aujourd'hui, régularité, programme)
 * et « Voir son espace » (ses pages, telles qu'il les voit, en lecture seule).
 */
class LearnerFollowController extends Controller
{
    public const FILTERS = ['pas-encore' => 'Pas encore de séance aujourd\'hui', 'faite' => 'Séance faite ou en cours'];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('aujourdhui'), self::FILTERS) ? (string) $request->query('aujourdhui') : null;
        $all = LearnerProgress::rows();

        return view('admin.follow.index', [
            'rows' => $filter === null ? $all : LearnerProgress::rows($filter),
            'counts' => LearnerProgress::counts($all),
            'filter' => $filter,
            'filters' => self::FILTERS,
        ]);
    }

    /** « Voir son espace » : administrateur principal seulement, compte d'apprenant seulement. */
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_unless(ViewAs::allowed($request->user(), $user), 403);

        ViewAs::start($request, $user);

        return to_route('dashboard')->with('status', 'Vous voyez l\'espace de '.$user->name.', en lecture seule.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $id = ViewAs::stop($request);
        $learner = $id === null ? null : User::query()->find($id);

        return $learner === null
            ? to_route('admin.follow.index')
            : redirect()->to(route('admin.users.show', $learner).'#progression');
    }
}
