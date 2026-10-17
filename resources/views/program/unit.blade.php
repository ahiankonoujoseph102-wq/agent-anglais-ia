<x-layout :title="'Unité '.$unit->number">
    <a class="back-link" href="{{ route('program.show') }}"><x-icon name="arrow-left" /> Mon programme</a>
    <div class="page-head rise">
        <span class="eyebrow">Niveau {{ $unit->level->value }} · unité {{ $unit->number }} sur 12</span>
        <h1>{{ $unit->title }}</h1>
    </div>

    <section class="card card-soft unit-section rise" style="--i: 1">
        <h2><x-icon name="target" /> À la fin, vous saurez…</h2>
        <ul>
            @foreach ($unit->objectives as $objective)
                <li>{{ $objective }}</li>
            @endforeach
        </ul>
    </section>

    <section class="card unit-section reveal">
        <h2><x-icon name="book" /> Vocabulaire clé</h2>
        <dl class="summary vocabulary">
            @foreach ($unit->vocabulary as $english => $french)
                <dt lang="en">{{ $english }}</dt><dd>{{ $french }}</dd>
            @endforeach
        </dl>
    </section>

    <section class="card unit-section reveal">
        <h2><x-icon name="edit" /> Grammaire</h2>
        <ul>@foreach ($unit->grammar as $point)<li>{{ $point }}</li>@endforeach</ul>
        <h2><x-icon name="mic" /> À l'oral</h2>
        <ul>@foreach ($unit->functions as $point)<li>{{ $point }}</li>@endforeach</ul>
        <h2><x-icon name="headphones" /> Prononciation</h2>
        <ul>@foreach ($unit->pronunciation as $point)<li>{{ $point }}</li>@endforeach</ul>
        @if ($unit->spoken)
            <h2><x-icon name="bubble" /> Comme les natifs</h2>
            <ul>@foreach ($unit->spoken as $point)<li>{{ $point }}</li>@endforeach</ul>
        @endif
        @if ($unit->pitfalls)
            <h2><x-icon name="alert" /> Attention, francophones !</h2>
            <ul>@foreach ($unit->pitfalls as $point)<li>{{ $point }}</li>@endforeach</ul>
        @endif
    </section>

    <section class="card unit-section reveal">
        <h2><x-icon name="globe" /> Situations de conversation</h2>
        @foreach ($unit->situations as $situation)
            <h3>{{ $situation->title }}</h3>
            <p>{{ $situation->description }}</p>
        @endforeach
    </section>
</x-layout>
