@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class(['is-invalid' => $errors->has($name)]) }}
    >
    @if ($hint)
        <div class="hint">{{ $hint }}</div>
    @endif
    @error($name)
        <div class="error" id="{{ $name }}-error">{{ $message }}</div>
    @enderror
</div>
