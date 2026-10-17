<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    /**
     * Permet à un inscrit de repasser l'évaluation (par exemple après une panne).
     * Le niveau du compte est effacé avec elle : l'ancien niveau n'est plus affiché ni
     * utilisé (programme, consignes du professeur) tant qu'une nouvelle évaluation
     * n'est pas terminée. « Mon espace » propose alors l'évaluation en premier.
     */
    public function destroy(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user) {
            $user->assessment()->delete();
            $user->level = null;
            $user->save();
        });

        return back()->with('status', "L'évaluation de {$user->name} a été réinitialisée : son espace lui propose de la repasser.");
    }
}
