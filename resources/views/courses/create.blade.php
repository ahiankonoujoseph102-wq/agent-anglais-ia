<x-layout title="Ajouter une leçon" narrow>
    <p><a class="back-link" href="{{ route('courses.index') }}"><x-icon name="arrow-left" /> Mes cours</a></p>

    <div class="page-head rise">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="camera" /></span>
            <h1>Ajouter une leçon</h1>
        </div>
        <p class="muted">
            Une leçon d'anglais de votre école, de votre lycée, de votre université ou de votre formation. {{ \App\Support\Teachers\Teachers::forUser(auth()->user())->name() }} la lit, puis vous aide à la comprendre et à vous exercer.
        </p>
    </div>

    @if ($error)
        <div class="alert alert-{{ $error['kind'] === 'unavailable' ? 'warning' : 'error' }} course-error" role="alert" data-course-error>
            <x-icon name="{{ $error['kind'] === 'not_english' ? 'info' : 'alert' }}" />
            <div>
                <strong>{{ $error['title'] }}</strong><br>
                {{ $error['message'] }}
                @if ($error['tips'] !== [])
                    <ul class="check-list">
                        @foreach ($error['tips'] as $tip)
                            <li><x-icon name="check" /> {{ $tip }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('courses.store') }}" enctype="multipart/form-data"
          class="card form-card rise course-form" id="course-form" novalidate data-course-form data-long-submit data-busy-label="Lecture de votre leçon…" data-timeout="{{ $limits['browser_timeout'] }}"
          data-max-photos="{{ $limits['photos'] }}" data-max-side="{{ $limits['max_side'] }}" data-quality="{{ $limits['quality'] }}"
          data-photo-max-bytes="{{ $limits['photo_max_bytes'] }}" data-pdf-max-bytes="{{ $limits['pdf_max_bytes'] }}">
        @csrf

        <h2 class="course-step">1. Choisissez comment envoyer votre leçon</h2>

        {{-- Trois choix. Les champs sont de vrais champs de fichier, habillés en boutons français ;
             sans JavaScript, la galerie et le PDF fonctionnent seuls (pages[] et pdf). --}}
        <div class="course-choices">
            <input id="course-camera" type="file" accept="image/*" capture="environment" class="file-pick-input" data-course-camera>
            <label for="course-camera" class="file-pick course-choice" data-course-camera-label>
                <span class="btn btn-accent btn-lg btn-block"><x-icon name="camera" /> Prendre une photo</span>
            </label>

            <input id="course-gallery" name="pages[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="file-pick-input" data-course-gallery
                   @error('pages') aria-invalid="true" aria-describedby="course-pages-error" @enderror>
            <label for="course-gallery" class="file-pick course-choice">
                <span class="btn btn-outline btn-lg btn-block"><x-icon name="image" /> Choisir dans la galerie</span>
            </label>

            <input id="course-pdf" name="pdf" type="file" accept="application/pdf" class="file-pick-input" data-course-pdf
                   @error('pdf') aria-invalid="true" aria-describedby="course-pdf-error" @enderror>
            <label for="course-pdf" class="file-pick course-choice">
                <span class="btn btn-outline btn-lg btn-block"><x-icon name="file-text" /> Ajouter un PDF</span>
            </label>
        </div>

        <p class="hint">
            Ajoutez jusqu'à {{ $limits['photos'] }} photos de votre cahier ou de votre livre (une par page), ou votre cours en PDF
            (jusqu'à {{ $limits['pdf_pages'] }} pages et {{ $limits['pdf_megabytes'] }} mégaoctets). Les photos sont réduites avant l'envoi pour économiser vos données.
            Un long PDF ? Vous choisirez ensuite la partie à travailler, {{ $limits['part_pages'] }} pages au plus à la fois.
        </p>

        @error('pages')
            <div class="error" id="course-pages-error">{{ $message }}</div>
        @enderror
        @error('pages.*')
            <div class="error">{{ $message }}</div>
        @enderror
        @error('pdf')
            <div class="error" id="course-pdf-error">{{ $message }}</div>
        @enderror

        {{-- Aperçu des pages choisies (rempli par public/js/course-upload.js). --}}
        <ol class="course-previews" data-course-previews hidden></ol>
        <p class="hint" data-course-status aria-live="polite" hidden></p>

        <div class="course-tips">
            <h3>Pour que {{ \App\Support\Teachers\Teachers::forUser(auth()->user())->name() }} lise bien</h3>
            <ul class="check-list">
                <li><x-icon name="check" /> Une photo par page, la page entière, sans rien couper.</li>
                <li><x-icon name="check" /> De la lumière, sans ombre sur le texte.</li>
                <li><x-icon name="check" /> Le téléphone bien à plat au-dessus de la page, l'image nette.</li>
            </ul>
        </div>

        <h2 class="course-step course-step-2">2. Un titre ? (facultatif)</h2>
        <x-input name="title" label="Titre de la leçon" :value="old('title')" maxlength="150"
                 :hint="\App\Support\Teachers\Teachers::forUser(auth()->user())->name().' en propose un d\'après votre leçon si vous n\'en donnez pas.'" />

        <p class="error" data-long-submit-error role="alert" hidden></p>
        <button type="submit" class="btn btn-accent btn-block btn-lg btn-plan course-submit" data-course-submit>Envoyer ma leçon</button>
        <p class="hint course-waiting" data-course-waiting hidden role="status">
            {{ \App\Support\Teachers\Teachers::forUser(auth()->user())->name() }} lit votre leçon. Cela peut prendre jusqu'à une minute pour un long PDF : gardez cette page ouverte.
        </p>
        <p class="muted small next-step">Vos pages restent privées : seuls vous et notre équipe pouvez les voir.</p>
    </form>

    {{-- Dans cet ordre : course-upload.js vérifie le choix, long-submit.js envoie en arrière-plan. --}}
    <script type="module" src="{{ \App\Support\Asset::url('js/course-upload.js') }}"></script>
    <script type="module" src="{{ \App\Support\Asset::url('js/long-submit.js') }}"></script>
</x-layout>
