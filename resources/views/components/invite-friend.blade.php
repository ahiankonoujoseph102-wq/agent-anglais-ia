{{--
    « Inviter un ami sur WhatsApp » (page de résultat de l'évaluation, « Mon espace ») : discret, aucun envoi
    automatique, aucune donnée envoyée à la plateforme. WhatsApp s'ouvre avec le message prérempli
    (App\Support\FriendInvite : ni nom ni niveau), l'apprenant choisit le contact.
--}}
<section {{ $attributes->class(['invite-friend']) }} aria-labelledby="inviter-un-ami">
    <h2 id="inviter-un-ami">Un ami veut parler anglais ?</h2>
    <p class="muted small">Invitez-le à passer l'évaluation orale gratuite avec {{ config('avatar.model.name') }}. Vous choisissez le contact dans WhatsApp.</p>
    <a class="btn btn-outline invite-friend-btn" href="{{ \App\Support\FriendInvite::url() }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Inviter un ami sur WhatsApp</a>
</section>
