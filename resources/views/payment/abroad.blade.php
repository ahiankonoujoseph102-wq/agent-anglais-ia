{{--
    Pays sans paiement Mobile Money sur le site : aucune capture. L'apprenant écrit à l'équipe sur WhatsApp
    (message prérempli : nom, pays, formule) ; l'administrateur active ensuite l'accès (« Offrir un accès »).
    Prix en francs CFA, jamais convertis.
--}}
<x-layout :title="'Formule '.$plan->name" narrow>
    <div class="page-head">
        <h1>Formule {{ $plan->name }} : {{ $plan->formattedPrice() }}</h1>
        <p class="muted">
            {{ $plan->dailyMinutes }} minutes par jour, à partager entre la séance orale et « Mes cours », pendant {{ $plan->durationDays }} jours.
            <a href="{{ route('payment.plans') }}">Changer de formule</a>
        </p>
    </div>

    <section class="card card-accent" role="status" aria-labelledby="abroad-title">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-accent"><x-icon name="globe" /></span>
            <h2 id="abroad-title">Pays : {{ $country }}</h2>
        </div>
        <p>Le paiement en ligne n'est pas encore disponible dans votre pays. Contactez-nous sur WhatsApp : nous vous expliquons comment vous abonner.</p>
        @if ($whatsappUrl)
            <a class="btn btn-whatsapp btn-block btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
            <p class="muted small next-step">Le message est déjà rédigé avec votre nom, votre pays et la formule choisie : il suffit de l'envoyer.</p>
        @else
            <a class="btn btn-block" href="{{ route('contact') }}">Voir nos coordonnées</a>
        @endif
    </section>
</x-layout>
