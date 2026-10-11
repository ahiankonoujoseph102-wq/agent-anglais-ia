<x-layout title="Vérifier une attestation" narrow>
    <h1>Vérification d'une attestation</h1>

    @if ($attestation)
        <div class="alert alert-success">
            <strong>Attestation authentique.</strong><br>
            {{ $attestation->user->name }} a validé le niveau <strong>{{ $attestation->level->value }} ({{ $attestation->level->label() }})</strong>
            en anglais avec {{ config('platform.name') }} le {{ $attestation->issued_at->translatedFormat('j F Y') }}.
        </div>
    @else
        <div class="alert alert-error">Aucune attestation ne correspond au code « {{ $code }} ».</div>
    @endif
</x-layout>
