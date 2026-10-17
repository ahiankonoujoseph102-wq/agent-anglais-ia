<x-layout title="Crédits" narrow :description="'Licences et auteurs des éléments utilisés par '.config('platform.name').'.'">
    <div class="page-head rise">
        <h1>Crédits</h1>
        <p class="muted">Le professeur animé ({{ $modelName }}), l'affichage en 3D et la police du site reposent sur les éléments suivants. Merci à leurs auteurs.</p>
    </div>

    @foreach ($credits as $credit)
        <section class="card credit reveal">
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
