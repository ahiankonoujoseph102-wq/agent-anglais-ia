{{--
    « Avant de commencer », replié : tout ce qui n'est pas l'action (ce qui va se passer, calme, micro, écouteurs, droit
    à l'erreur, connexion coupée). Rien n'est perdu, rien ne repousse « Commencer » hors de l'écran. Durées lues dans la
    configuration ($introMore vient du contrôleur).
--}}
<details class="before-start">
    <summary><x-icon name="info" /> <span>Avant de commencer</span></summary>
    <div class="before-start-body">
        @if ($mode === 'assessment')
            <p>{{ $teacherName ?? config('avatar.model.name') }} vous pose des questions adaptées à vos réponses. Ensuite : votre niveau, une mini-leçon sur vos propres erreurs et vos questions, {{ \App\Models\Assessment::totalMinutes() }} minutes au plus en tout.</p>
        @endif
        @if (! empty($introMore))
            <p>{{ $introMore }}</p>
        @endif
        <ul class="intro-tips">@include('live.tips')</ul>
    </div>
</details>
