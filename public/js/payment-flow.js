// Paiement en un seul enchaînement (page /abonnement/{formule}) : le pays, puis le moyen de
// paiement, puis le bloc à payer. Tous les blocs sont déjà dans la page (cachés sauf le choix
// en cours) : un toucher affiche le bloc suivant et y fait descendre la page, sans rechargement.
// Sans JavaScript, les mêmes liens rechargent la page avec une ancre vers le bon bloc.
//
// Bloc de paiement : la page descend jusqu'à lui, et assez pour que le bouton de l'étape ③ (« J'ai payé, j'envoie ma
// capture ») soit visible sans défiler. Retour depuis l'application Mobile Money (page de nouveau visible, ou
// rechargée) : le choix est gardé (adresse ?pays=…&moyen=…), la page revient sur l'étape ③ et le bouton est mis en
// évidence quelques secondes.
import { readAway, revealBetween, shouldReturn, writeAway } from './pay-view.js';

const flow = typeof document !== 'undefined' ? document.querySelector('[data-pay-flow]') : null;

if (flow) {
    const methods = flow.querySelector('[data-pay-methods]');
    const form = flow.querySelector('[data-proof-form]');
    const help = flow.querySelector('[data-pay-help]');
    const countryInput = flow.querySelector('[data-pay-country-input]');
    const methodInput = flow.querySelector('[data-pay-method-input]');
    const proofInput = flow.querySelector('[data-proof-input]');
    const pick = flow.querySelector('[data-proof-pick]');
    const last = flow.querySelector('[data-pay-last]');
    const lastTitle = flow.querySelector('[data-pay-last-title]');
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const storage = (() => { try { return window.sessionStorage; } catch { return null; } })();
    // Le choix de l'image ouvre la galerie : la page est alors cachée, ce n'est pas un départ vers Mobile Money.
    let picking = false;

    // Descend jusqu'au bloc (en douceur, ou d'un coup si « moins d'animations »), puis place
    // le focus sur son titre sans refaire défiler.
    const goTo = (block) => {
        const heading = block.querySelector('h2[tabindex]') ?? block;
        requestAnimationFrame(() => {
            block.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            heading.focus({ preventScroll: true });
        });
    };

    // Bloc de paiement : son haut sous l'en-tête, et le bouton de l'étape ③ dans la zone visible.
    const showPayment = (block, { instant = false } = {}) => {
        const heading = block.querySelector('h2[tabindex]') ?? block;
        requestAnimationFrame(() => {
            revealBetween(block, pick, { instant });
            heading.focus({ preventScroll: true });
        });
    };

    // Retour à l'étape ③ : son titre et le bouton visibles, le focus sur le titre (lu par les lecteurs d'écran),
    // le bouton mis en évidence quelques secondes.
    const backToProof = ({ instant = false } = {}) => {
        requestAnimationFrame(() => {
            revealBetween(last, pick, { instant });
            lastTitle?.focus({ preventScroll: true });
            pick?.classList.add('is-highlighted');
            setTimeout(() => pick?.classList.remove('is-highlighted'), 4000);
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
            showPayment(flow.querySelector(`[data-pay-block="${key}"]`));
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

    // Moyen de paiement choisi, capture pas encore choisie : c'est le moment où l'on part payer.
    const chosenKey = () => (!form.hidden && methodInput.value && !form.hasAttribute('data-has-file') ? `${countryInput.value}.${methodInput.value}` : null);

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
            // Le pays déjà connu est replié : « Modifier » le rouvre.
            target.hidden = false;
            goTo(target);
        }
    });

    // Choix de l'image : la galerie s'ouvre (la page est cachée un moment), puis la capture est choisie ou non.
    proofInput?.addEventListener('click', () => { picking = true; });
    proofInput?.addEventListener('change', () => { picking = false; writeAway(storage, null); });
    proofInput?.addEventListener('cancel', () => { picking = false; });

    // Départ vers l'application Mobile Money : la page est cachée (ou fermée par le téléphone) avec un moyen de
    // paiement choisi. Au retour, elle revient sur l'étape ③.
    const leave = () => {
        const key = chosenKey();

        if (key && !picking) {
            writeAway(storage, { key, path: location.pathname, at: Date.now() });
        }
    };

    const comeBack = (options) => {
        const key = chosenKey();

        if (!picking && shouldReturn(readAway(storage), { key, path: location.pathname, now: Date.now() })) {
            writeAway(storage, null);
            backToProof(options);

            return true;
        }

        return false;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            leave();
        } else {
            comeBack();
        }
    });
    window.addEventListener('pagehide', leave);
    // Page gardée en mémoire par le navigateur et réaffichée telle quelle.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) comeBack({ instant: true });
    });
    // Galerie fermée sans choisir d'image (navigateur sans évènement « cancel ») : on n'attend plus de capture.
    window.addEventListener('focus', () => setTimeout(() => { if (!proofInput?.files?.length) picking = false; }, 1000));

    // Page (re)chargée avec un moyen de paiement déjà choisi (adresse ?pays=…&moyen=…) : retour sur l'étape ③ si l'on
    // revient de l'application Mobile Money, sinon le bloc de paiement avec son bouton visible. Jamais par-dessus une
    // erreur à corriger (app.js y place déjà la page).
    if (!form.hidden && !flow.querySelector('[aria-invalid="true"], .error')) {
        if (!comeBack({ instant: true })) {
            const block = flow.querySelector(`[data-pay-block="${countryInput.value}.${methodInput.value}"]`);

            if (block) showPayment(block, { instant: true });
        }
    }
}
