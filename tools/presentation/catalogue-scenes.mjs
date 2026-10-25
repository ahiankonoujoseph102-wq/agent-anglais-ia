// Passages du CATALOGUE DES ÉCRANS (catalogue.mjs) : tout le site de DÉMONSTRATION, filmé « brut » pour un monteur.
// Aucune marque ajoutée (ni cercle, ni flèche, ni zoom, ni texte) : seulement le doigt au toucher et des défilements
// doux et lents. Chaque passage commence et finit par 2 secondes calmes (CALM). Comptes fictifs « Élève » de
// database/seeders/CatalogueDemoSeeder.php ; aucun paiement, aucune donnée réelle.
//
// Un passage : key (nom), title (ce qu'il montre), seconds (durée), account, path, live (séance avec le faux
// fournisseur), before (avant la première image, jamais filmé), act(s, ms), moments (moments forts, en secondes depuis
// le début du passage, pour l'index du monteur). « teacher: true » : Teacher Joe en grand (rendu à part, catalogue.mjs).

export const CALM = 2000;

const sleep = (ms) => new Promise((ok) => setTimeout(ok, Math.max(0, ms)));

/** Défilement doux jusqu'à « y » (pixels depuis le haut de la page), en « ms » millisecondes. */
async function scrollToY(s, y, ms) {
    await s.page.evaluate(([target, m]) => window.__fx.scrollBy(Math.round(target - window.scrollY), m), [y, ms]);
}

/** Hauteur défilable de la page. */
const maxScroll = (s) => s.page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);

/** Défilement lent jusqu'à un élément (« offset » pixels sous le haut de l'écran). */
async function slowTo(s, selector, ms, offset = 80) {
    await s.page.evaluate(([sel, m, o]) => window.__fx.scrollTo(sel, m, o), [selector, ms, offset]);
}

/** Toute la durée utile (entre les deux temps calmes) à défiler jusqu'en bas, doucement. */
async function scrollAll(s, ms, from = null) {
    if (from !== null) {
        await s.page.evaluate((y) => window.scrollTo(0, y), from);
    }

    await s.wait(CALM);
    await scrollToY(s, await maxScroll(s), ms - 2 * CALM);
}

/** Toucher visible (doigt et onde), avec ou sans action. */
const touch = (s, selector, options = {}) => s.tap(selector, { click: false, ...options });

/** Ce que dit le faux professeur (texte des sous-titres ; aucune voix : la vidéo est muette). */
const say = (s, teacher, seconds) => s.say({ teacher, seconds: seconds ?? Math.max(1.6, teacher.split(/\s+/).length / 2.4) });
const answer = (s, learner) => s.say({ learner });

/** Accueil en quatre parties : chacune défile un quart de la page, lentement. */
function homePart(part, parts) {
    return async (s, ms) => {
        const total = await maxScroll(s);
        const from = Math.round((total * (part - 1)) / parts);
        await s.page.evaluate((y) => window.scrollTo(0, y), from);
        await s.wait(CALM);
        await scrollToY(s, Math.round((total * part) / parts), ms - 2 * CALM);
    };
}

/** @param {{ attestationCode: string, courseId: number }} ctx */
export function passages(ctx) {
    return [
        {
            key: 'accueil-haut', title: 'Accueil, premier écran : Teacher Joe, l\'accroche, « Passer mon évaluation gratuite »', seconds: 12,
            account: 'visitor', path: '/',
            async act(s) {
                await s.page.evaluate(() => window.scrollTo(0, 0));
                await s.wait(CALM + 3000);
                await touch(s, '.hero-cta');
            },
            moments: [[0, 'premier écran, immobile'], [5.6, 'doigt sur « Passer mon évaluation gratuite »']],
        },
        ...[1, 2, 3, 4].map((part) => ({
            key: `accueil-defilement-${part}`, title: `Accueil, toute la page en défilement lent (partie ${part} sur 4)`, seconds: 20,
            account: 'visitor', path: '/',
            act: homePart(part, 4),
            moments: [[2, 'début du défilement'], [18, 'fin du défilement']],
        })),
        {
            key: 'teacher-joe-parle', title: 'Teacher Joe parle, en grand, lèvres synchronisées (son dans le fichier séparé)', seconds: 20, teacher: true,
            moments: [[2, 'début de sa voix'], [17.5, 'fin de sa voix']],
        },
        {
            key: 'inscription', title: 'Inscription : pays, nom, numéro, mot de passe, « Créer mon compte »', seconds: 20,
            account: 'visitor', path: '/inscription',
            async act(s) {
                await s.wait(CALM);
                await s.tap('#country', { click: false });
                await s.select('#country', 'TG');
                await s.wait(600);
                await s.tap('input[name="name"]');
                await s.type('input[name="name"]', 'Élève', 110);
                await s.tap('input[name="phone"]');
                await s.type('input[name="phone"]', s.newPhone(), 110);
                await slowTo(s, 'input[name="password"]', 1400, 180);
                await s.tap('input[name="password"]');
                await s.type('input[name="password"]', 'demo-presentation', 60);
                await s.tap('input[name="password_confirmation"]');
                await s.type('input[name="password_confirmation"]', 'demo-presentation', 60);
                await slowTo(s, 'main form button[type="submit"]', 1200, 360);
                await s.wait(500);
                await s.tap('main form button[type="submit"]', { navigate: true });
                await s.registered();
            },
            moments: [[2.5, 'choix du pays'], [3.5, 'saisie du nom et du numéro'], [9, 'mot de passe'], [15, 'toucher « Créer mon compte »'], [17, '« Mon espace » du nouveau compte']],
        },
        {
            key: 'evaluation-commencer', title: 'Évaluation gratuite : toucher « Passer mon évaluation gratuite », puis « Commencer »', seconds: 18,
            account: 'fresh', path: '/', live: 'assessment',
            async act(s) {
                await s.page.evaluate(() => window.scrollTo(0, 0));
                await s.wait(CALM + 500);
                await s.tap('.hero-cta', { navigate: true });
                await s.teacherReady();
                await s.wait(2500);
                await s.tap('#start');
                await s.wait(4000);
                await say(s, 'Hello! Welcome. I am your English teacher. What is your name?', 4);
            },
            moments: [[2.5, 'toucher « Passer mon évaluation gratuite »'], [4, 'page de l\'évaluation, Teacher Joe'], [8, 'toucher « Commencer »'], [12, 'Teacher Joe parle (sous-titres)']],
        },
        {
            key: 'evaluation-a-vous', title: 'Évaluation : « À vous de parler », la réponse de l\'élève, la question suivante', seconds: 18,
            live: 'assessment',
            async act(s) {
                await s.page.evaluate(() => window.scrollTo(0, 0));
                await s.wait(CALM + 2000);
                await answer(s, 'My name is Afi. I live in Lomé.');
                await s.wait(2500);
                await say(s, 'Nice to meet you, Afi! What do you do in Lomé?', 3.5);
                await s.wait(5000);
                await answer(s, 'I sell clothes at the market.');
            },
            moments: [[0, '« À vous de parler »'], [4, 'réponse de l\'élève en sous-titres'], [6.5, 'Teacher Joe répond'], [11, 'de nouveau « À vous de parler »']],
        },
        {
            key: 'evaluation-niveau', title: 'Évaluation : Teacher Joe annonce le niveau, la carte du résultat et la première phrase', seconds: 20,
            live: 'assessment',
            async act(s) {
                await s.page.evaluate(() => window.scrollTo(0, 0));
                await s.wait(CALM);
                await s.say({ call: { name: 'record_assessment_result', args: { level: 'A2', feedback: 'Vous comprenez les questions simples et vous répondez avec des phrases courtes.' } } });
                await s.wait(900);
                await say(s, 'Thank you! Your level is A2. Well done!', 3);
                await s.wait(6000);
                await say(s, 'Let me show you how we will work together. Repeat after me: Nice to meet you.', 4.5);
            },
            moments: [[3, 'Teacher Joe annonce le niveau'], [6.5, 'carte du résultat : niveau A2'], [9, 'tableau « Découverte », phrase à répéter']],
        },
        {
            key: 'evaluation-resultat', title: 'Page du résultat de l\'évaluation : niveau, avis de Teacher Joe, première leçon, formules', seconds: 20,
            account: 'assessed', path: '/evaluation',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'niveau A2'], [6, 'avis et première leçon'], [14, 'formules']],
        },
        {
            key: 'mon-espace', title: 'Mon espace : prochaine étape, temps du jour, suivi, attestations', seconds: 20,
            account: 'active', path: '/espace',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'prochaine étape : la séance du jour'], [8, 'chiffres et suivi'], [16, 'attestations et bas de page']],
        },
        {
            key: 'programme', title: 'Mon programme : la suite, le niveau, les douze unités', seconds: 20,
            account: 'active', path: '/programme',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, '« Votre prochaine leçon »'], [8, 'calendrier et unités']],
        },
        {
            key: 'programme-unite', title: 'Une unité du programme : objectifs, vocabulaire, leçons', seconds: 16,
            account: 'active', path: '/programme/unites/1',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre de l\'unité'], [6, 'objectifs et leçons']],
        },
        {
            key: 'niveaux', title: 'Les six niveaux, de A1 à C2 (échelle de l\'accueil)', seconds: 12,
            account: 'visitor', path: '/',
            async before(s) {
                await s.page.evaluate(() => document.querySelector('.level-dot')?.scrollIntoView({ block: 'center' }));
                await sleep(600);
            },
            async act(s) {
                await s.wait(CALM);
                await s.page.evaluate(() => window.__fx.scrollBy(160, 5000));
            },
            moments: [[0, 'échelle des niveaux']],
        },
        {
            key: 'seance-lecon', title: 'Séance du jour, la leçon : « Commencer », Teacher Joe et le tableau, la phrase dite s\'allume', seconds: 20,
            account: 'active', path: '/seance', live: 'lesson',
            async act(s) {
                await s.wait(CALM);
                await s.tap('#start');
                await s.wait(3500);
                await say(s, 'Hello! Today we have fifteen minutes together. Look at the board.', 4);
                await s.openBoard();
                await s.wait(4500);
                const phrase = await s.page.evaluate(() => document.querySelector('#board [data-board-item], #board li')?.textContent?.trim().split('\n')[0] ?? '');
                await say(s, `Repeat after me: ${phrase || 'Nice to meet you.'}`, 4);
            },
            moments: [[2, 'toucher « Commencer »'], [6, 'Teacher Joe accueille l\'élève'], [10, 'tableau de la leçon ouvert'], [12, 'la phrase dite s\'allume au tableau']],
        },
        {
            key: 'seance-pratique', title: 'Séance du jour, la pratique : « Now, let\'s practice! », l\'élève répète', seconds: 16,
            live: 'lesson',
            async act(s) {
                await s.wait(CALM);
                await say(s, "Now, let's practice! Repeat after me: Can you repeat, please?", 4.5);
                await s.wait(5500);
                await answer(s, 'Can you repeat, please?');
                await s.wait(1200);
                await say(s, 'Very good!', 1.6);
            },
            moments: [[2, '« Now, let\'s practice! », étape « Pratique » au tableau'], [9.5, 'l\'élève répète'], [11, 'Teacher Joe félicite']],
        },
        {
            key: 'seance-conversation', title: 'Séance du jour, la conversation : « Now, let\'s talk! », l\'élève parle de sa vie', seconds: 16,
            live: 'lesson',
            async act(s) {
                await s.wait(CALM);
                await say(s, "Now, let's talk! Tell me about your work at the market.", 4);
                await s.wait(5000);
                await answer(s, 'I sell clothes. I work every day.');
                await s.wait(1500);
                await say(s, 'Great! What do you sell the most?', 2.5);
            },
            moments: [[2, '« Now, let\'s talk! », étape « Conversation »'], [9, 'réponse de l\'élève'], [10.5, 'question suivante']],
        },
        {
            key: 'seance-bilan', title: 'Séance du jour, le bilan : « Let\'s sum up! », la séance se termine, « La suite »', seconds: 20,
            live: 'lesson',
            async act(s) {
                await s.wait(CALM);
                await say(s, "Let's sum up! Today, you can talk about your work. Remember: I sell clothes.", 5);
                await s.wait(5600);
                await s.say({
                    call: {
                        name: 'end_lesson',
                        args: {
                            lesson_status: 'completed', summary: '« I sell clothes. » Vous savez parler de votre travail.', homework: 'Dites trois phrases sur votre journée de travail.',
                            errors: [], reviewed_errors: [], scores: { fluency: 3, pronunciation: 3, grammar: 3, vocabulary: 3, comprehension: 4 },
                        },
                    },
                });
                await s.wait(900);
                await say(s, 'Goodbye! See you tomorrow.', 2.2);
            },
            moments: [[2, '« Let\'s sum up! », étape « Bilan »'], [9, 'au revoir de Teacher Joe'], [13, 'fin de la séance, carte « La suite »']],
        },
        {
            key: 'mes-cours-ajouter', title: 'Mes cours : « Ajouter une leçon », photo du cahier', seconds: 18,
            account: 'active', path: '/mes-cours',
            async act(s) {
                await s.wait(CALM);
                await s.tapText('Ajouter une leçon', { navigate: true });
                await s.wait(2500);
                await s.chooseCoursePhoto();
                await s.wait(2000);
                await slowTo(s, 'main form button[type="submit"]', 2500, 380);
                await s.wait(800);
                await touch(s, 'main form button[type="submit"]');
            },
            moments: [[2, 'toucher « Ajouter une leçon »'], [7, 'photo de la leçon ajoutée'], [13, 'bouton d\'envoi']],
        },
        {
            key: 'mes-cours-lecon', title: 'Mes cours : la leçon lue (résumé, grammaire, vocabulaire, exercices)', seconds: 18,
            account: 'active', path: `/mes-cours/${ctx.courseId}`,
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'la leçon « Le présent simple »'], [8, 'explications et exercices']],
        },
        {
            key: 'mes-cours-explication', title: 'Mes cours : la séance, Teacher Joe explique la leçon en français', seconds: 20,
            account: 'active', path: `/mes-cours/${ctx.courseId}/seance`, live: 'course',
            async act(s) {
                await s.wait(CALM);
                await s.tap('#start');
                await s.wait(4000);
                await say(s, 'Bonjour ! Aujourd\'hui, le présent simple. Avec he, she ou it, on ajoute un s : She works in a bank.', 7);
                await s.wait(8000);
                await answer(s, 'He plays football.');
            },
            moments: [[2, 'toucher « Commencer »'], [6.5, 'Teacher Joe explique en français'], [14.5, 'l\'élève donne un exemple']],
        },
        {
            key: 'mes-seances', title: 'Mes séances : résumés, devoirs, erreurs à revoir, documents de révision', seconds: 16,
            account: 'active', path: '/mes-seances',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'historique des séances'], [8, 'documents de révision']],
        },
        {
            key: 'attestations', title: 'Mes attestations de niveau (« Bravo, niveau A1 validé ! », « Mes attestations de niveau »)', seconds: 14,
            account: 'active', path: '/espace',
            async before(s) {
                await s.page.evaluate(() => document.querySelector('.attestation-fresh')?.scrollIntoView({ block: 'start' }));
                await s.page.evaluate(() => window.scrollBy(0, -80));
                await sleep(800);
            },
            async act(s) {
                await s.wait(CALM + 2000);
                await slowTo(s, 'section.card:has(h2:text-is("Mes attestations de niveau"))', 5000, 120).catch(() => {});
                await s.page.evaluate(() => {
                    const title = [...document.querySelectorAll('h2')].find((h) => h.textContent.includes('Mes attestations de niveau'));

                    return title && window.__fx.scrollTo(title.closest('section'), 4000, 120);
                });
            },
            moments: [[0, '« Bravo, niveau A1 validé ! »'], [8, '« Mes attestations de niveau »']],
        },
        {
            key: 'attestation-verification', title: 'Vérification publique d\'une attestation par son code', seconds: 12,
            account: 'visitor', path: `/attestation/verifier/${ctx.attestationCode}`,
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'attestation authentique, niveau et code']],
        },
        {
            key: 'formules', title: 'Formules : les trois cartes (Semaine, Essentiel, Intensif)', seconds: 18,
            account: 'visitor', path: '/formules',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre « Formules et prix »'], [5, 'les trois cartes'], [13, 'évaluation gratuite et pays']],
        },
        {
            key: 'abonnement', title: 'Page d\'abonnement : choisir sa formule, ligne « Des questions avant de choisir ? »', seconds: 16,
            account: 'assessed', path: '/abonnement',
            async act(s, ms) {
                await s.wait(CALM);
                await slowTo(s, '.plans-questions', ms - 2 * CALM - 2500, 300);
                await s.wait(800);
                await touch(s, '.plans-questions a');
            },
            moments: [[0, '« Choisissez votre formule »'], [5, 'les trois cartes'], [12.5, 'ligne « Des questions avant de choisir ? »']],
        },
        {
            key: 'paiement', title: 'Paiement Mobile Money : moyen de paiement, les trois étapes, le numéro, « J\'ai payé, j\'envoie ma capture »', seconds: 20,
            account: 'assessed', path: '/abonnement/essentiel',
            async act(s) {
                await s.wait(CALM);
                await s.tap('[data-pay-method="TG.mixx"]');
                await s.wait(2500);
                await slowTo(s, '[data-pay-block="TG.mixx"] .pay-choice', 2500, 70);
                await s.wait(2500);
                await slowTo(s, '#capture', 3500, 160);
                await s.wait(1200);
                await touch(s, '[data-proof-pick]');
            },
            moments: [[2, 'toucher « Mixx by Yas »'], [7, 'étape 1 : le numéro à copier'], [9, 'étape 2 : le montant'], [15, 'étape 3 : « J\'ai payé, j\'envoie ma capture »']],
        },
        {
            key: 'comment-ca-marche', title: 'Comment ça marche : les vidéos et le bouton « Regarder »', seconds: 20,
            account: 'visitor', path: '/comment-ca-marche',
            async act(s) {
                await s.wait(CALM);
                await slowTo(s, 'a.watch', 2500, 120);
                await s.wait(1500);
                await s.tap('a.watch');
                await s.wait(5000);
                await slowTo(s, '#talkami', 3500, 80);
            },
            moments: [[2, 'première vidéo'], [6, 'toucher « Regarder »'], [7, 'la vidéo démarre'], [12, 'vidéos suivantes']],
        },
        {
            key: 'installer', title: 'Installer l\'application (Android et iPhone)', seconds: 14,
            account: 'visitor', path: '/installer-l-application',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre et explications']],
        },
        {
            key: 'contact', title: 'Contact : WhatsApp, Joseph vous répond, TikTok et chaîne WhatsApp', seconds: 16,
            account: 'visitor', path: '/contact',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'bouton « Écrire sur WhatsApp »'], [6, 'liens TikTok et chaîne WhatsApp'], [10, 'coordonnées']],
        },
        {
            key: 'a-propos', title: 'À propos : le fondateur, pourquoi Talkami, comment fonctionne Teacher Joe', seconds: 18,
            account: 'visitor', path: '/a-propos',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre'], [5, 'le fondateur'], [12, 'comment fonctionne Teacher Joe']],
        },
        {
            key: 'administration-videos', title: 'Administration, page « Vidéos » : les vidéos de leçon de Teacher Joe (aucun chiffre de revenus)', seconds: 20,
            account: 'admin', path: '/admin/videos',
            async before(s) {
                await s.page.evaluate(() => document.getElementById('videos')?.scrollIntoView({ block: 'start' }));
                await s.page.evaluate(() => window.scrollBy(0, -70));
                await sleep(800);
            },
            async act(s, ms) {
                await s.wait(CALM);
                await s.page.evaluate((m) => window.__fx.scrollBy(Math.min(2600, document.documentElement.scrollHeight), m), ms - 2 * CALM);
            },
            moments: [[0, 'liste des vidéos du jour'], [6, 'aperçu et « Télécharger »'], [12, 'texte de publication']],
        },
        // Autres écrans utiles.
        {
            key: 'connexion', title: 'Connexion : numéro de téléphone et mot de passe', seconds: 12,
            account: 'visitor', path: '/connexion',
            async act(s) {
                await s.wait(CALM);
                await s.tap('input[name="phone"]');
                await s.type('input[name="phone"]', '99000005', 120);
                await s.tap('input[name="password"]');
                await s.type('input[name="password"]', 'demo-presentation', 60);
                await s.wait(600);
                await touch(s, 'main form button[type="submit"]');
            },
            moments: [[2, 'saisie du numéro'], [6, 'mot de passe'], [9.5, 'doigt sur « Se connecter »']],
        },
        {
            key: 'bienvenue', title: 'Bienvenue de Teacher Joe, après l\'ouverture d\'un accès', seconds: 14,
            account: 'welcome', path: '/bienvenue',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, '« Bienvenue, Élève ! »'], [6, 'bouton « Toucher pour écouter Teacher Joe »']],
        },
        {
            key: 'questionnaire', title: 'Questionnaire en quatre écrans (objectif, profil et métier, jours, difficultés)', seconds: 20,
            account: 'welcome', path: '/programme/questionnaire/1',
            async act(s) {
                await s.wait(CALM);

                const choose = async (selector) => {
                    const label = s.page.locator(selector).first().locator('xpath=ancestor::label[1]');
                    await s.tap((await label.count()) ? label : s.page.locator(selector).first());
                };
                const next = () => s.tap('main form button[type="submit"]', { navigate: true });

                await choose('input[name="goal"][value="work"]');
                await s.wait(900);
                await next();
                await s.wait(1000);
                await choose('input[name="profile"]');
                await s.tap('input[name="occupation"]');
                await s.type('input[name="occupation"]', 'Commerçante', 90);
                await next();
                await s.wait(2200);
                await next();
                await s.wait(1000);
                await choose('input[name="difficulties[]"]');
                await s.wait(800);
                await slowTo(s, 'main form button[type="submit"]', 1200, 420);
                await touch(s, 'main form button[type="submit"]');
            },
            moments: [[2, 'écran 1 : objectif'], [6, 'écran 2 : profil et métier'], [10, 'écran 3 : jours'], [14, 'écran 4 : difficultés']],
        },
        {
            key: 'pays-togo', title: 'Page « Apprendre l\'anglais au Togo » (une page par pays de paiement)', seconds: 16,
            account: 'visitor', path: '/apprendre-anglais/togo',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre et boutons'], [8, 'moyens de paiement et formules']],
        },
        {
            key: 'questions-frequentes', title: 'Accueil, questions fréquentes : deux questions ouvertes', seconds: 16,
            account: 'visitor', path: '/',
            async before(s) {
                await s.page.evaluate(() => document.querySelector('.faq')?.scrollIntoView({ block: 'start' }));
                await s.page.evaluate(() => window.scrollBy(0, -70));
                await sleep(800);
            },
            async act(s) {
                await s.wait(CALM);
                await s.tap('.faq details summary');
                await s.wait(4000);
                await s.tap(s.page.locator('.faq details summary').nth(1));
                await s.wait(1500);
            },
            moments: [[2, 'première question ouverte'], [7, 'deuxième question ouverte']],
        },
        {
            key: 'confidentialite', title: 'Confidentialité (page publique)', seconds: 12,
            account: 'visitor', path: '/confidentialite',
            act: (s, ms) => scrollAll(s, ms, 0),
            moments: [[0, 'titre']],
        },
    ];
}
