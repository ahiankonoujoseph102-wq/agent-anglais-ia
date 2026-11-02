<x-layout title="Ne plus recevoir ces e-mails" narrow :indexable="false">
    <section class="card">
        @if ($done)
            <h1>C'est noté</h1>
            <p>Vous ne recevrez plus nos e-mails de relance. Votre compte reste ouvert : vous pouvez revenir quand vous voulez.</p>
            <a class="btn btn-outline btn-block" href="{{ route('home') }}">Retour à l'accueil</a>
        @else
            <h1>Ne plus recevoir ces e-mails ?</h1>
            <p>Vous ne recevrez plus nos e-mails de relance (évaluation, séance d'essai). Votre compte reste ouvert.</p>
            <form method="POST" action="{{ $action }}">
                @csrf
                <button type="submit" class="btn btn-accent btn-block">Ne plus recevoir ces e-mails</button>
            </form>
        @endif
    </section>
</x-layout>
