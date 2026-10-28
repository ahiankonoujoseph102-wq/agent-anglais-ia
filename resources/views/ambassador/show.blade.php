<x-layout title="Espace ambassadeur" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble"><x-icon name="award" /></span>
        <h1>Espace ambassadeur</h1>
        <p>Partagez votre lien ou donnez votre code : chaque inscription faite avec eux vous est attribuée.</p>
    </div>

    {{-- Lien et code personnels : la première chose à partager. --}}
    <section class="card ambassador-link rise" style="--i: 1" aria-labelledby="link-title">
        <h2 id="link-title">Votre lien personnel</h2>
        <p class="ambassador-url"><a href="{{ $ambassador->link() }}">{{ $ambassador->shortLink() }}</a></p>
        <a class="btn btn-whatsapp btn-block btn-lg" href="{{ $shareUrl }}" target="_blank" rel="noopener"><x-icon name="share" /> Partager mon lien sur WhatsApp</a>
        <button type="button" class="btn btn-outline btn-block next-step" data-copy="{{ $ambassador->link() }}" data-copied="Lien copié"><x-icon name="copy" /> Copier mon lien</button>

        <h2 class="next-step">Votre code</h2>
        <p class="ambassador-code" aria-describedby="code-help"><strong>{{ $ambassador->code }}</strong></p>
        <p id="code-help" class="muted small">À donner de vive voix : la personne le tape dans « Code ambassadeur » en créant son compte.</p>
    </section>

    {{-- Code QR pour le terrain : affiché, et téléchargeable avec le lien et le code écrits dessous. --}}
    <section class="card ambassador-qr rise" style="--i: 2" aria-labelledby="qr-title">
        <h2 id="qr-title">Votre code QR</h2>
        <p class="muted small">Pour le terrain : la personne le scanne avec l'appareil photo de son téléphone et arrive sur {{ config('platform.name') }} avec votre lien.</p>
        <div class="qr-frame">{!! $qr !!}</div>
        <a class="btn btn-outline btn-block" href="{{ route('ambassador.qr') }}" download><x-icon name="download" /> Télécharger mon code QR</a>
    </section>

    <section class="card rise" style="--i: 3" aria-labelledby="access-title">
        <h2 id="access-title">Votre accès gratuit</h2>
        <dl class="summary">
            <dt>Accès</dt>
            <dd>
                @if ($grant)
                    Formule {{ $grant->plan_name }}, jusqu'au {{ $grant->ends_at->translatedFormat('j F Y') }}
                @else
                    Aucun mois gratuit en cours
                @endif
            </dd>
            <dt>Mois offerts</dt><dd>{{ $ambassador->months_granted }} sur {{ config('platform.ambassadors.max_months') }}</dd>
            <dt>Vos séances</dt>
            <dd>
                {{ $activity['days'] }} {{ $activity['days'] > 1 ? 'jours' : 'jour' }} avec une séance du jour sur les {{ config('platform.ambassadors.activity_days') }} derniers jours
            </dd>
        </dl>
        <p class="muted small">Votre accès gratuit est renouvelé chaque mois si vous respectez les conditions du programme.</p>
    </section>

    <div class="form-footer">
        <p><a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> Retour à mon espace</a></p>
    </div>

    <script src="{{ \App\Support\Asset::url('js/copy.js') }}" defer></script>
</x-layout>
