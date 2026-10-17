{{-- Confirmation d'une action grave quand le JavaScript ne s'est pas chargé (middleware RequireConfirmation). --}}
<x-layout :title="$title" narrow>
    <section class="card confirm-page rise" aria-labelledby="confirm-title">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-danger"><x-icon name="alert" /></span>
            <h1 id="confirm-title">{{ $title }}</h1>
        </div>
        <p>{{ $message }}</p>

        <form method="POST" action="{{ $action }}" class="confirm-actions">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="confirmed" value="1">
            <input type="hidden" name="_origin" value="{{ $back }}">
            <button type="submit" class="btn btn-danger btn-block">{{ $confirm }}</button>
            <a href="{{ $back }}" class="btn btn-light btn-block">Annuler</a>
        </form>
    </section>
</x-layout>
