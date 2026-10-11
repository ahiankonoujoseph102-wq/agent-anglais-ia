<x-layout :title="'Unité '.$unit->number">
    <p><a href="{{ route('program.show') }}">← Mon programme</a></p>
    <h1>Unité {{ $unit->number }} · {{ $unit->title }}</h1>
    <p class="muted">Niveau {{ $unit->level->value }}</p>

    <section class="card">
        <h2>À la fin, vous saurez…</h2>
        <ul>
            @foreach ($unit->objectives as $objective)
                <li>{{ $objective }}</li>
            @endforeach
        </ul>
    </section>

    <section class="card">
        <h2>Vocabulaire clé</h2>
        <dl class="summary vocabulary">
            @foreach ($unit->vocabulary as $english => $french)
                <dt lang="en">{{ $english }}</dt><dd>{{ $french }}</dd>
            @endforeach
        </dl>
    </section>

    <section class="card">
        <h2>Grammaire</h2>
        <ul>@foreach ($unit->grammar as $point)<li>{{ $point }}</li>@endforeach</ul>
        <h2>À l'oral</h2>
        <ul>@foreach ($unit->functions as $point)<li>{{ $point }}</li>@endforeach</ul>
        <h2>Prononciation</h2>
        <ul>@foreach ($unit->pronunciation as $point)<li>{{ $point }}</li>@endforeach</ul>
        @if ($unit->spoken)
            <h2>Comme les natifs</h2>
            <ul>@foreach ($unit->spoken as $point)<li>{{ $point }}</li>@endforeach</ul>
        @endif
        @if ($unit->pitfalls)
            <h2>Attention, francophones !</h2>
            <ul>@foreach ($unit->pitfalls as $point)<li>{{ $point }}</li>@endforeach</ul>
        @endif
    </section>

    <section class="card">
        <h2>Situations de conversation</h2>
        @foreach ($unit->situations as $situation)
            <h3>{{ $situation->title }}</h3>
            <p>{{ $situation->description }}</p>
        @endforeach
    </section>
</x-layout>
