<x-layout title="Accès administrateur" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="lock" /></span>
        <h1>Retrouver l'accès administrateur</h1>
        <p>Touchez « Me connecter » : le site vous demandera aussitôt de choisir un nouveau mot de passe.</p>
    </div>

    @error('token')
        <p class="error" role="alert">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.recovery.store') }}" class="card form-card rise" style="--i: 1" id="recovery-form" hidden>
        @csrf
        <input type="hidden" name="token" id="recovery-token">
        <button type="submit" class="btn btn-accent btn-block btn-lg">Me connecter</button>
    </form>

    <p class="card rise" id="recovery-missing" style="--i: 1">Ouvrez cette page avec le bouton « Retrouver mon accès » de l'e-mail que vous avez reçu.</p>

    {{-- Le jeton est après « # » : jamais envoyé au serveur dans l'adresse ; retiré de l'adresse aussitôt lu. --}}
    <script>
        (function () {
            var token = location.hash.slice(1);
            if (!/^[a-f0-9]{64}$/.test(token)) return;
            document.getElementById('recovery-token').value = token;
            document.getElementById('recovery-form').hidden = false;
            document.getElementById('recovery-missing').hidden = true;
            history.replaceState(null, '', location.pathname);
        })();
    </script>
</x-layout>
