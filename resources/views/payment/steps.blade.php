{{-- Fil d'étapes du paiement. $current : 1 à 4. --}}
<ol class="pay-steps" aria-label="Étapes du paiement">
    @foreach (['Formule', 'Pays', 'Paiement', 'Validation'] as $i => $label)
        <li @class(['done' => $i + 1 < $current, 'current' => $i + 1 === $current]) @if ($i + 1 === $current) aria-current="step" @endif>
            <span>{{ $i + 1 }}</span> {{ $label }}
        </li>
    @endforeach
</ol>
