<?php

namespace App\Http\Controllers;

use App\Support\Teachers\Teachers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Choix du professeur dans « Mon espace » (config/teachers.php) : il vaut pour les PROCHAINES séances (séance du jour,
 * « Mes cours », bilan de niveau) ; une séance commencée garde son professeur. Seuls les professeurs prêts (voix et
 * visage) peuvent être choisis ; niveau, programme, progression et erreurs à revoir sont communs à tous.
 */
class TeacherChoiceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(Teachers::hasChoice(), 404);

        $key = (string) $request->validate([
            'teacher' => ['required', 'string', Rule::in(array_keys(Teachers::choosable()))],
        ], [
            'teacher.*' => 'Choisissez un des professeurs proposés.',
        ])['teacher'];

        $request->user()->forceFill(['teacher' => $key])->save();
        $teacher = Teachers::forUser($request->user());

        return redirect()->to(route('dashboard').'#professeur')
            ->with('status', 'Votre professeur est maintenant '.$teacher->name().' : '.$teacher->pronoun().' vous accompagne dès votre prochaine séance. Votre niveau, votre programme et vos erreurs à revoir ne changent pas.');
    }
}
