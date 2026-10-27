{{--
    Vérification d'une attestation (publique, jamais indexée). Aperçu de partage (WhatsApp) propre au niveau : titre,
    niveau et date, image « Niveau … validé » ; jamais le nom du titulaire dans l'aperçu (il n'apparaît que sur la page).
--}}
@php
    use App\Support\ShareLinks;

    $shareTitle = $attestation ? 'Attestation de niveau '.$attestation->level->value.' en anglais' : 'Vérifier une attestation';
    $shareDescription = $attestation
        ? 'Niveau '.$attestation->level->value.' ('.mb_strtolower($attestation->level->label()).') validé avec '.config('platform.name').' le '.$attestation->issued_at->translatedFormat('j F Y').'. Attestation authentique, vérifiée par son code.'
        : null;
@endphp

<x-layout :title="$shareTitle" :description="$shareDescription" narrow :share="(bool) $attestation"
          :share-image="$attestation ? ShareLinks::attestationImage($attestation->level) : null"
          :share-alt="$attestation ? 'Niveau '.$attestation->level->value.' validé avec '.config('platform.name') : null">
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
                @if (auth()->id() === $attestation->user_id)
                    {{-- Le titulaire seulement : partage volontaire sur WhatsApp. --}}
                    <a class="btn btn-outline btn-block" href="{{ ShareLinks::attestationUrl($attestation) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Partager mon attestation</a>
                @endif
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
