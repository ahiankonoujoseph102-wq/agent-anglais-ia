{{--
    Invitation à installer l'application, au bon moment (fin de l'évaluation, première séance, page de résultat) :
    UNE ligne discrète sous le bouton principal, jamais à sa place. Cachée dans l'application installée
    (classe is-standalone ou is-installed, public/css/app.css).
--}}
@props(['teacherName' => null])
<p {{ $attributes->merge(['class' => 'install-hint']) }} data-install-hint>
    <x-icon name="download" />
    <span>Installez {{ config('platform.name') }} pour retrouver {{ $teacherName ?? \App\Support\Teachers\Teachers::forUser(auth()->user())->name() }} en un geste. <a href="{{ route('install') }}" data-install-link>Installer l'application</a></span>
</p>
