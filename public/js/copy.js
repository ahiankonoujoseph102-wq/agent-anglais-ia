/*
 * Boutons « Copier » : copie la valeur de data-copy dans le presse-papiers.
 */
document.querySelectorAll('[data-copy]').forEach((button) => {
    const label = button.textContent;

    button.addEventListener('click', async () => {
        const value = button.dataset.copy;

        try {
            await navigator.clipboard.writeText(value);
        } catch {
            // Ancien navigateur : on passe par une zone de texte temporaire.
            const area = document.createElement('textarea');
            area.value = value;
            document.body.append(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }

        button.textContent = 'Numéro copié : ' + value;
        setTimeout(() => { button.textContent = label; }, 2500);
    });
});
