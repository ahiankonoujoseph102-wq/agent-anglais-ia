<x-layout title="Crédits" :description="'Licences et auteurs des éléments utilisés par '.config('platform.name').'.'">
    <h1>Crédits</h1>

    <p>Le professeur animé ({{ $modelName }}) et l'affichage en 3D reposent sur les éléments suivants. Merci à leurs auteurs.</p>

    @foreach ($credits as $credit)
        <section class="card">
            <h2>{{ $credit['name'] }}</h2>
            <dl class="summary">
                <dt>Auteur</dt>
                <dd>
                    @if ($credit['url'])
                        <a href="{{ $credit['url'] }}" target="_blank" rel="noopener">{{ $credit['author'] }}</a>
                    @else
                        {{ $credit['author'] }}
                    @endif
                </dd>
                <dt>Licence</dt>
                <dd><a href="{{ $credit['license_url'] }}" target="_blank" rel="noopener">{{ $credit['license'] }}</a></dd>
            </dl>
        </section>
    @endforeach
</x-layout>
