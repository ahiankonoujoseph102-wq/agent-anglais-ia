/*
 * Page « Vidéos » de l'administration : la case « Publiée » s'enregistre dès qu'on la touche (sans JavaScript,
 * le bouton « Enregistrer » reste là).
 */
document.querySelectorAll('[data-autosubmit]').forEach((box) => {
    box.form?.querySelector('[data-autosubmit-fallback]')?.setAttribute('hidden', '');
    box.addEventListener('change', () => box.form.requestSubmit());
});
