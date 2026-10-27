{{-- Partie « Guides » (publique, indexée) : la liste des guides gratuits (App\Support\Guides). --}}
@php
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
@endphp

<x-layout title="Guides gratuits pour apprendre l'anglais"
          :description="'Guides gratuits pour parler anglais : se présenter, phrases du quotidien, entretien, erreurs fréquentes, anglais au travail, en anglais américain.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="book" /></span>
        <h1>Guides gratuits pour apprendre l'anglais</h1>
        <p>Des conseils concrets et des phrases prêtes à dire, en anglais américain. Lisez, puis entraînez-vous à l'oral avec {{ $teacher }}.</p>
    </div>

    <section class="section" aria-label="Les guides">
        <ul class="guide-list">
            @foreach ($guides as $guide)
                <li class="card guide-card reveal" style="--i: {{ $loop->index % 4 }}">
                    <span class="icon-bubble"><x-icon :name="$guide->icon" /></span>
                    <div>
                        <h2><a href="{{ route('guides.show', $guide->slug) }}">{{ $guide->heading }}</a></h2>
                        <p>{{ $guide->lead }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="section" aria-labelledby="guides-plus">
        <h2 id="guides-plus">Pour aller plus loin</h2>
        <ul class="link-list">
            <li><a href="{{ route('courses-help') }}">Aide aux cours d'anglais, pour les élèves, les étudiants et les parents</a></li>
            <li><a href="{{ route('interview') }}">Préparer un entretien en anglais avec {{ $teacher }}</a></li>
            <li><a href="{{ route('how') }}">Voir comment se passe une séance, en vidéo</a></li>
        </ul>
    </section>

    <x-guide-cta />

    @push('head')
        {!! Seo::jsonLd(Seo::breadcrumbs(['Guides' => route('guides.index', [], false)])) !!}
    @endpush
</x-layout>
