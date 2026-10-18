{{--
    Champ de formulaire avec son libellé, son aide et son erreur.
    type="password" : bouton « œil » dans le champ pour afficher ou masquer ce qui est tapé
    (piloté par public/js/app.js ; sans JavaScript, le bouton reste caché).
--}}
@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

@php
    $describedBy = trim(($hint ? $name.'-hint ' : '').($errors->has($name) ? $name.'-error' : ''));
    // Premier champ en erreur de la page : il reçoit le focus, même sans JavaScript.
    $autofocus = $errors->has($name) && ! request()->attributes->get('first-error-focused');

    if ($autofocus) {
        request()->attributes->set('first-error-focused', true);
    }

    // Formulaire revenu avec des erreurs : seul le premier champ en erreur garde le focus automatique.
    if ($errors->any()) {
        $attributes = $attributes->except('autofocus');
    }
@endphp

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    @if ($type === 'password')
        <div class="password-field">
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @error($name) aria-invalid="true" @enderror
        @if ($autofocus) autofocus @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(['is-invalid' => $errors->has($name)]) }}
    >
    @if ($type === 'password')
            <button type="button" class="password-toggle" data-password-toggle="{{ $name }}" aria-controls="{{ $name }}"
                    aria-label="Afficher le mot de passe" title="Afficher le mot de passe" hidden>
                <x-icon name="eye" data-password-show />
                <x-icon name="eye-off" data-password-hide hidden />
            </button>
        </div>
    @endif
    @if ($hint)
        <div class="hint" id="{{ $name }}-hint">{{ $hint }}</div>
    @endif
    @error($name)
        <div class="error" id="{{ $name }}-error">{{ $message }}</div>
    @enderror
</div>
