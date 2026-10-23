<x-layout title="Vérifier une attestation" narrow>
    @if ($attestation)
        <section class="empty-state rise">
            <span class="icon-bubble"><x-icon name="shield" /></span>
            <h1>Attestation authentique</h1>
            <div class="card card-soft">
                <p>
                    {{ $attestation->user->name }} a validé le niveau <strong>{{ $attestation->level->value }} ({{ $attestation->level->label() }})</strong>
                    en anglais avec {{ config('platform.name') }} le {{ $attestation->issued_at->translatedFormat('j F Y') }}.
                </p>
                <p class="muted small">Code de vérification : <span class="attestation-code">{{ $attestation->code }}</span></p>
            </div>
            <div class="actions">
                <a class="btn btn-accent btn-block" href="{{ route('home') }}"><x-icon name="home" /> Découvrir {{ config('platform.name') }}</a>
            </div>
        </section>
    @else
        <section class="empty-state rise">
            <span class="icon-bubble icon-bubble-danger"><x-icon name="alert" /></span>
            <h1>Vérification d'une attestation</h1>
            <div class="alert alert-error"><x-icon name="alert" /><div>Aucune attestation ne correspond au code « {{ $code }} ». Vérifiez le code écrit en bas de l'attestation.</div></div>
            <div class="actions">
                <a class="btn btn-accent btn-block" href="{{ route('home') }}"><x-icon name="home" /> Retour à l'accueil</a>
                <a class="btn btn-outline btn-block" href="{{ route('contact') }}">Nous contacter</a>
            </div>
        </section>
    @endif
</x-layout>
