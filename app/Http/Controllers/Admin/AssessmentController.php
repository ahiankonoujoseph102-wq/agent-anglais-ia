<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AssessmentController extends Controller
{
    /**
     * Permet à un inscrit de repasser l'évaluation (par exemple après une panne).
     * Le niveau déjà enregistré sur le compte est conservé.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->assessment()->delete();

        return back()->with('status', "L'évaluation de {$user->name} a été réinitialisée.");
    }
}
