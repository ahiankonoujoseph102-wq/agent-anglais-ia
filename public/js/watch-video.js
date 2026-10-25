// « Comment ça marche » : la vidéo n'existe dans la page qu'après le toucher sur « Regarder » (aucune donnée dépensée
// avant). Le lien est remplacé par un lecteur qui démarre aussitôt ; sans JavaScript, le lien ouvre la vidéo.

export function watch(link, doc = document) {
    const video = doc.createElement('video');
    video.className = 'watch-player';
    video.controls = true;
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.preload = 'auto';
    video.width = Number(link.querySelector('img')?.getAttribute('width')) || 480;
    video.height = Number(link.querySelector('img')?.getAttribute('height')) || 854;
    video.setAttribute('aria-label', link.dataset.title || 'Vidéo');
    video.src = link.href;
    link.replaceWith(video);
    video.focus?.();
    video.play?.()?.catch?.(() => {});

    return video;
}

for (const link of typeof document === 'undefined' ? [] : document.querySelectorAll('a[data-watch]')) {
    link.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        watch(link);
    });
}
