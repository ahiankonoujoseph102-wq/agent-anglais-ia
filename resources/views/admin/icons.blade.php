<x-layout title="Aperçu des icônes">
    @include('admin.nav')

    <h1>Aperçu des icônes</h1>
    <p class="muted">
        Les mêmes icônes (famille Lucide) dans les trois styles de pastille. Le style appliqué sur tout le site
        est réglé par une seule valeur : <code>icon_style</code> dans <code>config/platform.php</code>
        (« night », « gradient » ou « duotone »), puis un nouveau déploiement.
    </p>

    @foreach ($styles as $key => $label)
        <section class="icon-preview" data-icon-style="{{ $key }}" aria-labelledby="style-{{ $key }}">
            <div class="icon-preview-head">
                <h2 id="style-{{ $key }}">{{ $loop->iteration }}. {{ $label }}</h2>
                @if ($key === $current)
                    <span class="badge badge-accent">Style appliqué sur le site</span>
                @else
                    <span class="muted small">Valeur : « {{ $key }} »</span>
                @endif
            </div>

            <div class="features icon-preview-grid">
                @foreach ($samples as $icon => [$title, $text])
                    <article class="card feature">
                        <span @class(['icon-bubble', 'icon-bubble-primary' => $loop->first])><x-icon :name="$icon" /></span>
                        <div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Autres usages du même style : étape, chiffre de « Mon espace », question fréquente. --}}
            <div class="icon-preview-extras">
                <div class="card">
                    <div class="how-head"><span class="how-num">1</span><span class="icon-bubble"><x-icon name="user-plus" /></span></div>
                    <h3>Créez votre compte</h3>
                </div>
                <div class="stat">
                    <div class="label"><x-icon name="clock" /> Temps restant aujourd'hui</div>
                    <div class="value">15 minutes</div>
                </div>
                <div class="faq">
                    <details>
                        <summary><x-icon name="wallet" /><span>Comment payer mon abonnement ?</span></summary>
                        <p>Par Mobile Money, depuis votre téléphone.</p>
                    </details>
                </div>
            </div>
        </section>
    @endforeach
</x-layout>
