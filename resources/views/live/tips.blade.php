{{-- Conseils avant une conversation (carte d'accueil, et sous Teacher Joe sur ordinateur). Évaluation : le calme, le
     micro et le droit à l'erreur sont déjà dits au-dessus de « Commencer » (live/assessment-before). --}}
@php($before = ($mode ?? null) === 'assessment')
@unless ($before)
    <li><x-icon name="pin" /> Installez-vous dans un endroit calme.</li>
@endunless
<li><x-icon name="headphones" /> Avec des écouteurs, le professeur vous entendra mieux.</li>
<li><x-icon name="bubble" /> Quand {{ config('avatar.model.name') }} parle, votre micro est coupé : attendez « À vous de parler » pour répondre.</li>
@unless ($before)
    <li><x-icon name="mic" /> Votre navigateur va vous demander l'accès au micro : acceptez-le.</li>
@endunless
@if (isset($mode) && \App\Support\SessionRecording::enabled($mode))
    <li><x-icon name="video" /> Touchez « Enregistrer ma séance » pour garder la vidéo de votre séance avec {{ config('avatar.model.name') }} sur votre téléphone, et la revoir pour mesurer vos progrès.</li>
@endif
@unless ($before)
    <li><x-icon name="messages" /> Vous pouvez parler français, demander de répéter ou de réexpliquer : vous avez le droit de vous tromper.</li>
@endunless
<li><x-icon name="wifi" /> {{ config('platform.live.disconnect_rule') }}</li>
