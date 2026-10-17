{{-- Fil d'étapes animé (paiement, questionnaire). $labels : intitulés ; $current : 1…n. --}}
@props(['labels', 'current', 'label'])

@php
    $count = count($labels);
    $to = round(($current / $count) * 100);
    $from = round((max(0, $current - 1) / $count) * 100);
@endphp

<div class="stepper rise">
    <div class="stepper-bar" style="--from: {{ $from }}%; --to: {{ $to }}%" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $current }}" aria-valuemin="1" aria-valuemax="{{ $count }}"><span></span></div>
    <ol class="pay-steps" aria-label="{{ $label }}">
        @foreach ($labels as $i => $text)
            <li @class(['done' => $i + 1 < $current, 'current' => $i + 1 === $current]) @if ($i + 1 === $current) aria-current="step" @endif>
                <span>@if ($i + 1 < $current)<x-icon name="check" />@else{{ $i + 1 }}@endif</span> {{ $text }}
            </li>
        @endforeach
    </ol>
</div>
