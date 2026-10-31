{{-- Suivi de l'élève payant (App\Support\Reminders\LessonReminders) : heure de ses séances, rappels sur ce téléphone,
     adresse e-mail à ajouter. Rien pour un élève qui n'est pas abonné payant ou n'a pas encore de programme. --}}
@props(['user' => auth()->user()])
@php
    use App\Support\Reminders\LessonReminders;
    $program = LessonReminders::follows($user) ? $user->activeProgram()->first() : null;
    $time = $program ? LessonReminders::studyTime($user) : null;
    $teacherName = \App\Support\Teachers\Teachers::forUser($user)->name();
    $pushReady = \App\Support\Alerts\WebPushTransport::configured();
@endphp
@if ($program)
    <section {{ $attributes->merge(['class' => 'card lesson-reminders']) }} id="rappels" aria-labelledby="rappels-title">
        <div class="card-title"><span class="icon-bubble"><x-icon name="clock" /></span><h2 id="rappels-title">Heure de mes séances</h2></div>

        @if (blank($user->email))
            <div class="alert alert-warning">
                <x-icon name="mail" />
                <div>Ajoutez votre adresse e-mail pour recevoir vos rappels de séance. <a href="{{ route('account.edit') }}">Ajouter mon e-mail</a></div>
            </div>
        @endif

        @if ($time)
            <p>Vos séances : {{ $program->studyDaysPhrase() }}, à <strong>{{ LessonReminders::timeLabel($time) }}</strong>. {{ $teacherName }} vous envoie un rappel {{ config('platform.reminders.soon_minutes') }} minutes avant.</p>
        @else
            <p>Vous n'avez pas encore choisi l'heure de vos séances. Choisissez-la : {{ $teacherName }} vous enverra un rappel avant chaque séance, {{ $program->studyDaysPhrase() }}.</p>
        @endif

        <form method="POST" action="{{ route('reminders.time') }}" class="reminders-time">
            @csrf
            @method('PUT')
            <x-input name="study_time" :label="$time ? 'Changer l\'heure' : 'À quelle heure ?'" type="time" :value="$time ?? '18:00'" />
            <button type="submit" class="btn btn-outline btn-block">Enregistrer l'heure</button>
        </form>

        @if ($pushReady)
            <div class="reminders-push" data-reminders hidden
                 data-public-key="{{ config('alerts.vapid.public_key') }}"
                 data-subscribe-url="{{ route('reminders.device') }}"
                 data-unsubscribe-url="{{ route('reminders.device.remove') }}">
                <button type="button" class="btn btn-outline btn-block" data-reminders-enable><x-icon name="phone" /> Recevoir mes rappels sur ce téléphone</button>
                <p class="muted small" data-reminders-on hidden><x-icon name="check-circle" /> Rappels activés sur ce téléphone. <button type="button" class="btn btn-link" data-reminders-disable>Arrêter</button></p>
                <p class="muted small" data-reminders-note hidden></p>
            </div>
            <script type="module" src="{{ \App\Support\Asset::url('js/learner-reminders.js') }}"></script>
        @endif
    </section>
@endif
