{{--
    Évaluation gratuite, AVANT « Commencer » : ce qui va se passer, le calme, le droit à l'erreur, et le micro expliqué
    avant que le téléphone ne demande l'autorisation (la demande arrive au toucher de « Commencer »). Durées lues dans
    la configuration (platform.assessment.max_minutes, Assessment::totalMinutes()). Le micro n'est ouvert que pendant
    la séance et coupé à la fin (live-session.js).
--}}
@php($teacher = config('avatar.model.name'))
<ul class="intro-before" aria-label="Avant de commencer">
    <li><x-icon name="list" /> <span>{{ $teacher }} vous pose des questions, des plus simples aux plus difficiles. Ensuite : votre niveau, une première phrase apprise et vos questions, {{ \App\Models\Assessment::totalMinutes() }} minutes au plus en tout.</span></li>
    <li><x-icon name="pin" /> <span>Installez-vous dans un endroit calme.</span></li>
    <li><x-icon name="messages" /> <span>Vous avez le droit de vous tromper : répondez comme vous pouvez, même en français si vous bloquez.</span></li>
    <li class="intro-mic"><x-icon name="mic" /> <span><strong>Le micro :</strong> après « Commencer », votre téléphone vous demande d'autoriser le micro. Touchez « Autoriser ». Il ne sert que pendant la séance, pour que {{ $teacher }} vous entende.</span></li>
</ul>
