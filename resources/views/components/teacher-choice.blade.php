{{--
    Choix du professeur (« Mon espace », #professeur) : montré seulement quand au moins deux professeurs sont prêts
    (config/teachers.php → ready : voix ET visage prêts). Vaut pour la séance du jour, « Mes cours » et le bilan de
    niveau ; l'évaluation gratuite se choisit sur sa propre page (Teacher Joe proposé d'avance). Tout le reste est commun.
--}}
@if (\App\Support\Teachers\Teachers::hasChoice() && auth()->user()?->activeSubscription)
    @php($current = \App\Support\Teachers\Teachers::forUser(auth()->user()))
    {{-- Arrivé par « Changer » d'une page de séance : le choix y ramène (adresse vérifiée par le contrôleur). --}}
    @php($back = \App\Http\Controllers\TeacherChoiceController::back(request()->query('retour')))
    <section id="professeur" {{ $attributes->merge(['class' => 'card teacher-choice']) }} aria-labelledby="teacher-choice-title">
        <div class="card-title">
            <span class="icon-bubble"><x-icon name="user" /></span>
            <h2 id="teacher-choice-title">Votre professeur</h2>
        </div>
        <p>Choisissez avec qui vous faites votre séance du jour et « Mes cours ». Vous pouvez changer chaque jour : votre niveau, votre programme et vos erreurs à revoir restent les mêmes.</p>
        <div class="teacher-choice-list">
            @foreach (\App\Support\Teachers\Teachers::choosable() as $teacher)
                <form method="POST" action="{{ route('teacher.choose') }}" class="teacher-choice-item">
                    @csrf
                    <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                    @if ($back)
                        <input type="hidden" name="retour" value="{{ $back }}">
                    @endif
                    @if ($teacher->key === $current->key)
                        <p class="teacher-choice-current"><x-icon name="check-circle" /> <strong>{{ $teacher->name() }}</strong> <span class="muted">Votre professeur</span></p>
                    @else
                        {{-- Au toucher : « Teacher Rose arrive… » (le nom reste affiché), jamais le texte générique des envois. --}}
                        <button type="submit" class="btn btn-outline btn-block" data-busy-label="{{ $teacher->name() }} arrive…">Choisir {{ $teacher->name() }}</button>
                    @endif
                </form>
            @endforeach
        </div>
    </section>
@endif
