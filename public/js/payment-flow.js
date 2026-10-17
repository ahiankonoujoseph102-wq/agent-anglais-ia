// Paiement en un seul enchaînement (page /abonnement/{formule}) : le pays, puis le moyen de
// paiement, puis le bloc à payer. Tous les blocs sont déjà dans la page (cachés sauf le choix
// en cours) : un toucher affiche le bloc suivant et y fait descendre la page, sans rechargement.
// Sans JavaScript, les mêmes liens rechargent la page avec une ancre vers le bon bloc.
const flow = document.querySelector('[data-pay-flow]');

if (flow) {
    const methods = flow.querySelector('[data-pay-methods]');
    const form = flow.querySelector('[data-proof-form]');
    const help = flow.querySelector('[data-pay-help]');
    const countryInput = flow.querySelector('[data-pay-country-input]');
    const methodInput = flow.querySelector('[data-pay-method-input]');
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    // Descend jusqu'au bloc (en douceur, ou d'un coup si « moins d'animations »), puis place
    // le focus sur son titre sans refaire défiler.
    const goTo = (block) => {
        const heading = block.querySelector('h2[tabindex]') ?? block;
        requestAnimationFrame(() => {
            block.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            heading.focus({ preventScroll: true });
        });
    };

    const mark = (links, isCurrent) => {
        links.forEach((link) => {
            const current = isCurrent(link);
            link.classList.toggle('btn-light', !current);
            current ? link.setAttribute('aria-current', 'true') : link.removeAttribute('aria-current');
        });
    };

    // Fil d'étapes en haut de la page : 2 (pays et moyen de paiement) ou 3 (paiement).
    const setStep = (step) => {
        document.querySelectorAll('.pay-steps li').forEach((item, index) => {
            item.classList.toggle('done', index + 1 < step);
            item.classList.toggle('current', index + 1 === step);
            index + 1 === step ? item.setAttribute('aria-current', 'step') : item.removeAttribute('aria-current');
        });
    };

    const chooseMethod = (key, scroll = true) => {
        const [country, method] = key.split('.');
        mark(flow.querySelectorAll('[data-pay-method]'), (link) => link.dataset.payMethod === key);
        flow.querySelectorAll('[data-pay-block]').forEach((block) => { block.hidden = block.dataset.payBlock !== key; });
        countryInput.value = country;
        methodInput.value = method;
        form.hidden = false;
        if (help) help.hidden = false;
        setStep(3);

        if (scroll) {
            goTo(flow.querySelector(`[data-pay-block="${key}"]`));
        }
    };

    const chooseCountry = (code) => {
        mark(flow.querySelectorAll('[data-pay-country]'), (link) => link.dataset.payCountry === code);
        flow.querySelectorAll('[data-pay-methods-for]').forEach((group) => { group.hidden = group.dataset.payMethodsFor !== code; });
        methods.hidden = false;

        const options = flow.querySelectorAll(`[data-pay-methods-for="${code}"] [data-pay-method]`);

        // Un seul moyen de paiement dans ce pays : il est choisi d'office.
        if (options.length === 1) {
            chooseMethod(options[0].dataset.payMethod);

            return options[0].href;
        }

        form.hidden = true;
        if (help) help.hidden = true;
        methodInput.value = '';
        mark(options, () => false);
        setStep(2);
        goTo(flow.querySelector(`[data-pay-methods-for="${code}"]`));

        return null;
    };

    // L'adresse suit le choix (une page rechargée retrouve le même état), sans faire défiler.
    const remember = (href) => {
        if (href) history.replaceState(null, '', href.split('#')[0]);
    };

    flow.addEventListener('click', (event) => {
        const country = event.target.closest('[data-pay-country]');
        const method = event.target.closest('[data-pay-method]');
        const edit = event.target.closest('[data-pay-edit]');

        if (country) {
            event.preventDefault();
            remember(chooseCountry(country.dataset.payCountry) ?? country.href);
        } else if (method) {
            event.preventDefault();
            chooseMethod(method.dataset.payMethod);
            remember(method.href);
        } else if (edit) {
            event.preventDefault();
            const target = edit.dataset.payEdit === 'pays'
                ? flow.querySelector('#pays')
                : flow.querySelector(`[data-pay-methods-for="${countryInput.value}"]`);
            goTo(target);
        }
    });
}
