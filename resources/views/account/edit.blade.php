<x-layout title="Mon compte" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble"><x-icon name="user" /></span>
        <h1>Mon compte</h1>
        <p>Vous vous connectez toujours avec votre numéro de téléphone.</p>
    </div>

    <section class="card form-card rise" style="--i: 1">
        <dl class="summary">
            <dt>Nom</dt><dd>{{ $user->name }}</dd>
            <dt>Téléphone</dt><dd>{{ $user->phone }}</dd>
        </dl>

        <form method="POST" action="{{ route('account.update') }}" class="next-step" novalidate>
            @csrf
            @method('PUT')

            <x-input name="email" label="Adresse e-mail" type="email" inputmode="email" autocomplete="email"
                     :value="$user->email" :hint="\App\Support\EmailAddress::HINT" />

            <button type="submit" class="btn btn-block btn-lg">Enregistrer</button>
        </form>
    </section>

    <div class="form-footer">
        <p><a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> Retour à mon espace</a></p>
    </div>
</x-layout>
