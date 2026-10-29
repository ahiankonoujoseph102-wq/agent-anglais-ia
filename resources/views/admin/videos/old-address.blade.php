{{-- Vidéo qui dit et affiche encore l'ancienne adresse du site (script gardé à sa préparation) : à refaire, avec l'accord du propriétaire. --}}
@if ($video->showsOldAddress())
    <p class="small"><span class="badge badge-alert">Ancienne adresse</span> Cette vidéo dit et affiche {{ implode(', ', \App\Support\Videos\OldAddress::hosts()) }} : à refaire pour qu'elle montre {{ \App\Support\Videos\OldAddress::primary() }}.</p>
@endif
