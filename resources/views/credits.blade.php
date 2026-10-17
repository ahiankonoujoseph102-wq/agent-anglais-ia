<x-layout title="Crédits" narrow :description="'Licences et auteurs des éléments utilisés par '.config('platform.name').'.'">
    <div class="page-head rise">
        <h1>Crédits</h1>
        <p class="muted">Le professeur animé ({{ $modelName }}), l'affichage en 3D, la police et les photos du site reposent sur les éléments suivants. Merci à leurs auteurs.</p>
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

    <h2 class="next-step">Photos</h2>
    <p class="muted">Photos libres de droit publiées sur Unsplash, sous la licence Unsplash (usage commercial permis).</p>
    @foreach ($photos as $name => $photo)
        <section class="card credit credit-photo reveal">
            <figure class="photo-frame"><x-photo :name="$name" /></figure>
            <dl class="summary">
                <dt>Photo</dt><dd><a href="{{ $photo['source_url'] }}" target="_blank" rel="noopener">{{ $photo['alt'] }}</a></dd>
                <dt>Auteur</dt><dd><a href="{{ $photo['author_url'] }}" target="_blank" rel="noopener">{{ $photo['author'] }}</a></dd>
                <dt>Licence</dt><dd><a href="https://unsplash.com/license" target="_blank" rel="noopener">Licence Unsplash</a></dd>
            </dl>
        </section>
    @endforeach
</x-layout>
