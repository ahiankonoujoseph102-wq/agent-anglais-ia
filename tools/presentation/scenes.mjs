// Scènes des vidéos de présentation : ce que l'écran du site de DÉMONSTRATION montre pendant chaque phrase de
// Teacher Joe (repère « scene » des scripts de resources/videos/presentations.php). Chaque scène reçoit l'aide « s »
// (make.mjs : doigt, cercle, flèche, zoom, défilement, saisie, changement de compte) et sa durée en millisecondes.
//
// « account » : compte de démonstration utilisé (database/seeders/PresentationDemoSeeder.php), « visitor » : sans compte.
// « live » : la scène fait partie d'une séance avec le faux fournisseur (fake-gemini.mjs) ; « plan » donne ce que le
// faux professeur dit et quand (millisecondes depuis le début de la scène).

const LEVEL_FEEDBACK = 'Vous comprenez les questions simples et vous répondez avec des phrases courtes.';

/** Garde le début d'une page visible : en haut, sans zoom ni marque. */
async function top(s) {
    await s.page.evaluate(() => window.scrollTo(0, 0));
}

export const SCENES = {
    // Plans « plein écran » (Teacher Joe seul) : rien à l'écran du téléphone.
    wait: { full: true },
    'prepare.home': { full: true },
    'prepare.dashboard': { full: true },
    'prepare.plans': { full: true },
    'prepare.pay': { full: true },

    // Accueil (visiteur)
    'home.audiences': {
        account: 'visitor', path: '/',
        async act(s, ms) {
            await s.wait(400);
            await s.circle('.hero-cta');
            await s.wait(Math.min(1500, ms * 0.2));
            await s.clear();
            await s.scroll('.audiences', Math.min(2200, ms * 0.3), 80);
            await s.circle('section.audience:first-of-type');
        },
    },
    'home.course': {
        account: 'visitor', path: '/',
        async act(s, ms) {
            await s.scroll('#cours', Math.min(1800, ms * 0.3));
            await s.circle('.course-steps');
            await s.wait(Math.min(2500, ms * 0.35));
            await s.clear();
            await s.scroll('.board-preview', 1200, 140);
            await s.circle('.board-preview');
        },
    },
    'home.start': {
        account: 'visitor', path: '/',
        async act(s) {
            await top(s);
            await s.wait(500);
            await s.arrow('.hero-cta');
            await s.wait(1200);
            await s.tap('.hero-cta');
        },
    },

    // Inscription (visiteur) : un numéro fictif tout neuf à chaque vidéo.
    'register.fill': {
        account: 'visitor', path: '/inscription',
        async act(s, ms) {
            await s.select('#country', 'TG');
            await s.type('input[name="name"]', 'Élève', 70);
            await s.type('input[name="phone"]', s.newPhone(), 70);
            await s.scroll('input[name="password"]', 700, 160);
            await s.type('input[name="password"]', 'demo-presentation', 45);
            await s.type('input[name="password_confirmation"]', 'demo-presentation', 45);
            await s.wait(Math.max(0, ms - 9000));
        },
    },
    'register.country': {
        account: 'visitor', path: '/inscription',
        async act(s) {
            await s.circle('#country');
            await s.wait(700);
            await s.tap('#country', { click: false });
            await s.select('#country', 'TG');
        },
    },
    'register.identity': {
        account: 'visitor', path: '/inscription',
        async act(s) {
            await s.tap('input[name="name"]');
            await s.type('input[name="name"]', 'Élève', 90);
            await s.tap('input[name="phone"]');
            await s.type('input[name="phone"]', s.newPhone(), 90);
        },
    },
    'register.email': {
        account: 'visitor', path: '/inscription',
        async act(s) {
            await s.scroll('input[name="email"]', 700, 200);
            await s.circle('input[name="email"]', 10);
        },
    },
    'register.password': {
        account: 'visitor', path: '/inscription',
        async act(s) {
            await s.scroll('input[name="password"]', 700, 200);
            await s.tap('input[name="password"]');
            await s.type('input[name="password"]', 'demo-presentation', 50);
            await s.tap('input[name="password_confirmation"]');
            await s.type('input[name="password_confirmation"]', 'demo-presentation', 50);
        },
    },
    'register.submit': {
        account: 'visitor', path: '/inscription',
        async before(s) {
            // Formulaire déjà rempli (scènes précédentes, ou ici si la vidéo commence là).
            if (!(await s.page.inputValue('input[name="name"]'))) {
                await s.page.selectOption('#country', 'TG');
                await s.page.fill('input[name="name"]', 'Élève');
                await s.page.fill('input[name="phone"]', s.newPhone());
                await s.page.fill('input[name="password"]', 'demo-presentation');
                await s.page.fill('input[name="password_confirmation"]', 'demo-presentation');
            }
        },
        async act(s) {
            await s.scroll('form button[type="submit"]', 700, 380);
            await s.arrow('form button[type="submit"]');
            await s.wait(900);
            await s.clear();
            await s.tap('form button[type="submit"]', { navigate: true });
            await s.registered();
        },
    },

    // Mon espace
    'dashboard.next': {
        account: 'registered', path: '/espace',
        async act(s) {
            await s.wait(500);
            await s.circle('.next-card');
            await s.wait(1500);
            await s.arrow('.next-card .btn-next');
        },
    },
    'dashboard.assessment': {
        account: 'fresh', path: '/espace',
        async act(s, ms) {
            await s.wait(400);
            await s.circle('.next-card');
            await s.wait(Math.min(2500, ms * 0.5));
            await s.clear();
            await s.tap('.next-card .btn-next', { navigate: true });
        },
    },
    'dashboard.lesson': {
        account: 'active', path: '/espace',
        async act(s) {
            await s.wait(400);
            await s.circle('.next-card');
            await s.wait(1300);
            await s.clear();
            await s.tap('.next-card .btn-next', { navigate: true });
        },
    },

    // Évaluation (faux fournisseur, compte « fresh »)
    'assessment.intro': {
        account: 'fresh', path: '/evaluation', live: 'assessment',
        async act(s) {
            await s.teacherReady();
            await s.arrow('#start');
            await s.wait(1000);
            await s.clear();
            await s.tap('#start');
        },
    },
    'assessment.calm': {
        account: 'fresh', path: '/evaluation', live: 'assessment',
        async act(s) {
            await s.teacherReady();
            await s.circle('#start-block');
        },
    },
    'assessment.mic': {
        account: 'fresh', path: '/evaluation', live: 'assessment',
        async act(s) {
            await s.teacherReady();
            await s.circle('#start-block .hint, #start-block p:last-child');
            await s.wait(1200);
            await s.clear();
            await s.tap('#start');
        },
    },
    'assessment.talk': {
        live: 'assessment',
        plan: [{ at: 300, teacher: 'Hello! Welcome. What is your name?', seconds: 3 }, { at: 4200, learner: 'My name is Afi.' }, { at: 5200, teacher: 'Nice to meet you, Afi! Where do you live?', seconds: 3 }],
        async act(s) {
            await s.wait(2600);
            await s.circle('#turn');
        },
    },
    'assessment.turns': {
        live: 'assessment',
        plan: [{ at: 300, teacher: 'Hello! Welcome. What is your name?', seconds: 3.2 }],
        async act(s) {
            await s.wait(1200);
            await s.circle('#turn');
            await s.wait(2600);
            await s.clear();
            await s.circle('#turn');
        },
    },
    'assessment.phrases': {
        live: 'assessment',
        plan: [{ at: 200, learner: 'I live in Lomé. I sell clothes at the market.' }, { at: 1400, teacher: 'Very good! What do you like to do on weekends?', seconds: 3.2 }],
        async act(s) {
            await s.scroll('#useful-phrases', 900, 260);
            await s.circle('#useful-phrases');
        },
    },
    'assessment.verdict': {
        live: 'assessment',
        plan: [
            { at: 100, call: { name: 'record_assessment_result', args: { level: 'A2', feedback: LEVEL_FEEDBACK } } },
            { at: 900, teacher: 'Thank you! Your level is A2. Well done!', seconds: 3 },
        ],
        async act(s) {
            await s.page.evaluate(() => window.scrollTo(0, 0));
            await s.wait(4300);
            await s.circleAny(['#outcome:not([hidden])', '#timer']);
        },
    },
    'assessment.discovery': {
        live: 'assessment',
        plan: [{ at: 200, teacher: 'Let me show you how we will work together. Repeat after me: Nice to meet you.', seconds: 4.5 }],
        async act(s) {
            await s.wait(800);
            await s.circleAny(['#board', '#board-home .board', '#discovery-board']);
        },
    },
    'assessment.questions': {
        live: 'assessment',
        plan: [{ at: 300, learner: 'Combien de temps dure une séance ?' }, { at: 1300, teacher: 'Quinze ou trente minutes par jour, selon votre formule.', seconds: 3.5 }],
        async act(s) {
            await s.wait(600);
            await s.circleAny(['#timer-label', '#timer']);
        },
    },
    'assessment.total': {
        live: 'assessment',
        async act(s) {
            await s.circleAny(['#timer']);
        },
    },

    // Résultat de l'évaluation et formules (compte « assessed »)
    'result.level': {
        account: 'assessed', path: '/evaluation',
        async act(s, ms) {
            await s.wait(400);
            await s.circleAny(['.result-level', '.result-hero h1', 'main h1']);
            await s.wait(Math.min(2500, ms * 0.45));
            await s.clear();
            await s.scrollText(['Choisir un autre niveau'], 1200);
        },
    },
    'result.plans': {
        account: 'assessed', path: '/evaluation',
        async act(s) {
            await s.scroll('#formules', 1200, 90);
            await s.circle('.plan.is-recommended');
        },
    },
    'plans.semaine': { account: 'assessed', path: '/abonnement', async act(s) { await s.planCard(0); } },
    'plans.essentiel': { account: 'assessed', path: '/abonnement', async act(s) { await s.planCard(1); } },
    'plans.intensif': { account: 'assessed', path: '/abonnement', async act(s) { await s.planCard(2); } },
    'plans.shared': {
        account: 'assessed', path: '/abonnement',
        async act(s) {
            await s.planCard(1, false);
            await s.circleText('.plan.is-recommended', 'partager');
        },
    },
    'plans.midnight': {
        account: 'assessed', path: '/abonnement',
        async act(s) {
            await s.planCard(1, false);
            await s.zoom('.plan.is-recommended', 1.25);
        },
    },
    'plans.same': {
        account: 'assessed', path: '/formules',
        async act(s) {
            await s.wait(300);
            await s.scrollText(['Togo', 'Bénin', 'Burkina Faso'], 1500);
        },
    },
    'plans.pick': {
        account: 'assessed', path: '/abonnement',
        async act(s) {
            await s.planCard(1, false);
            await s.arrow('.plan.is-recommended .btn-plan');
            await s.wait(1000);
            await s.clear();
            await s.tap('.plan.is-recommended .btn-plan', { navigate: true });
        },
    },
    'plans.choose': {
        account: 'assessed', path: '/abonnement',
        async act(s) {
            await s.planCard(1, false);
            await s.tap('.plan.is-recommended .btn-plan', { navigate: true });
        },
    },

    // Paiement (compte « assessed », Togo : jamais d'envoi)
    'pay.method': {
        account: 'assessed', path: '/abonnement/essentiel',
        async act(s) {
            await s.wait(400);
            await s.circleAny(['[data-pay-methods-for="TG"] .pay-choice']);
            await s.wait(1300);
            await s.clear();
            await s.tap('[data-pay-method="TG.mixx"]');
        },
    },
    'pay.countries': {
        account: 'assessed', path: '/abonnement/essentiel?pays=TG#moyen',
        async act(s) {
            await s.wait(300);
            await s.circle('[data-pay-methods-for="TG"]');
        },
    },
    'pay.step1': {
        account: 'assessed', path: '/abonnement/essentiel?pays=TG&moyen=mixx#paiement',
        async act(s) {
            await s.scroll('[data-pay-block="TG.mixx"] .pay-number', 700, 150);
            await s.zoom('[data-pay-block="TG.mixx"] .pay-number', 1.45);
            await s.wait(1400);
            await s.unzoom();
            await s.wait(500);
            await s.tap('[data-pay-block="TG.mixx"] [data-copy]');
        },
    },
    'pay.step2': {
        account: 'assessed', path: '/abonnement/essentiel?pays=TG&moyen=mixx#paiement',
        async act(s, ms) {
            await s.scroll('[data-pay-block="TG.mixx"] .pay-big', 700, 220);
            await s.zoom('[data-pay-block="TG.mixx"] .pay-step-title:has(.pay-big)', 1.6);
            await s.wait(Math.min(2600, ms * 0.45));
            await s.unzoom();
            await s.wait(400);
            await s.circle('[data-pay-block="TG.mixx"] .pay-step-text');
        },
    },
    'pay.step3': {
        account: 'assessed', path: '/abonnement/essentiel?pays=TG&moyen=mixx#paiement',
        async act(s) {
            await s.scroll('#capture', 700, 200);
            await s.zoom('[data-proof-pick]', 1.35);
            await s.wait(1600);
            await s.unzoom();
            await s.arrow('[data-proof-pick]');
        },
    },
    'pay.send': {
        account: 'assessed', path: '/abonnement/essentiel?pays=TG&moyen=mixx#capture',
        async act(s) {
            await s.scroll('#capture', 500, 200);
            await s.tap('[data-proof-pick]', { click: false });
            await s.chooseDemoProof();
            await s.wait(1500);
            await s.arrow('[data-proof-submit]');
        },
    },
    'pay.outcomes': {
        account: 'pending', path: '/espace',
        async act(s) {
            await s.wait(400);
            await s.circle('.next-card');
        },
    },
    'pay.abroad': {
        account: 'abroad', path: '/abonnement/essentiel',
        async act(s) {
            await s.wait(400);
            await s.circleAny(['.btn-whatsapp']);
        },
    },

    // Après le paiement (compte « welcome » : bienvenue, questionnaire)
    'welcome.page': {
        account: 'welcome', path: '/bienvenue',
        async act(s) {
            await s.wait(500);
            await s.circleAny(['[data-welcome-play]', '#welcome-play', '.welcome .btn-accent']);
        },
    },
    'questionnaire.all': {
        account: 'welcome', path: '/programme/questionnaire/1',
        async act(s, ms) {
            const step = Math.max(1500, (ms - 600) / 4);
            await s.wait(500);

            for (let screen = 1; screen <= 4; screen++) {
                await s.questionnaireScreen(screen);
                await s.wait(Math.max(0, step - 1800));
            }
        },
    },

    // Programme, séances, « Mes cours » (compte « active »)
    'program.show': {
        account: 'active', path: '/programme',
        async act(s, ms) {
            await s.wait(500);
            await s.circle('#suite, .next-card, main h1');
            await s.wait(Math.min(2000, ms * 0.3));
            await s.clear();
            await s.page.evaluate(() => window.__fx.scrollBy(900, 2400));
            await s.wait(2500);
        },
    },
    'program.goals': {
        account: 'active', path: '/programme',
        async act(s) {
            await s.scrollText(['Modifier mes objectifs'], 900);
        },
    },
    'lesson.board': {
        account: 'active', path: '/seance', live: 'lesson',
        plan: [{ at: 1500, teacher: "Hello! Today we have fifteen minutes. Let's look at the board.", seconds: 4 }],
        async act(s) {
            await s.teacherReady();
            await s.tap('#start');
            await s.wait(3500);
            await s.openBoard();
            await s.circle('#board');
        },
    },
    'lesson.start': {
        account: 'active', path: '/seance', live: 'lesson',
        plan: [{ at: 1500, teacher: "Hello! Today we have fifteen minutes together. Let's start.", seconds: 4 }],
        async act(s) {
            await s.teacherReady();
            await s.arrow('#start');
            await s.wait(900);
            await s.clear();
            await s.tap('#start');
            await s.wait(2500);
            await s.circle('#timer');
        },
    },
    'lesson.teach': {
        live: 'lesson',
        plan: [{ at: 200, teacher: 'First, the board. Repeat after me: Nice to meet you.', seconds: 4 }],
        async act(s) {
            await s.openBoard();
            await s.circle('#board');
        },
    },
    'lesson.phrases': {
        live: 'lesson',
        async act(s) {
            await s.scroll('#useful-phrases', 900, 220);
            await s.page.evaluate(() => { document.getElementById('useful-phrases')?.setAttribute('open', ''); });
            await s.circleText('#useful-phrases', 'repeat');
        },
    },
    'lesson.summary': {
        live: 'lesson',
        plan: [{ at: 200, teacher: "Let's sum up! Today, you can introduce yourself.", seconds: 3.5 }],
        async act(s) {
            await s.page.evaluate(() => window.scrollTo(0, 0));
            await s.wait(1200);
            await s.circleAny(['#board .board-head', '#board']);
        },
    },
    'lesson.timer': {
        live: 'lesson',
        async act(s) {
            await s.scroll('#timer', 600, 300);
            await s.circle('#timer');
        },
    },
    'lesson.cut': {
        live: 'lesson',
        async act(s) {
            await s.scroll('#timer', 600, 300);
            await s.zoom('#timer', 1.3);
        },
    },
    'lesson.pause': {
        live: 'lesson',
        async act(s) {
            await s.unzoom();
            await s.scroll('#pause', 600, 300);
            await s.circle('#pause');
        },
    },
    'lesson.record': {
        live: 'lesson',
        async act(s) {
            await s.scroll('#recorder', 800, 250);
            await s.circle('#record-start');
        },
    },
    'lesson.next': {
        account: 'active', path: '/mes-seances',
        async act(s) {
            await s.wait(400);
            await s.circleAny(['.lesson-history article', 'main .card']);
        },
    },
    'history.show': {
        account: 'active', path: '/mes-seances',
        async act(s, ms) {
            await s.wait(400);
            await s.circleAny(['main .card']);
            await s.wait(Math.min(2000, ms * 0.4));
            await s.clear();
            await s.page.evaluate(() => window.__fx.scrollBy(500, 1500));
        },
    },
    'attestation.show': {
        account: 'active', path: '/espace',
        async act(s) {
            await s.wait(300);
            await s.scrollText(['Attestation', 'attestation'], 1200);
        },
    },
    'courses.menu': {
        account: 'active', path: '/espace',
        async act(s) {
            await s.wait(300);
            await s.tap('summary.menu-button');
            await s.wait(700);
            await s.circle('.menu-panel a[href$="/mes-cours"]');
            await s.wait(1000);
            await s.tap('.menu-panel a[href$="/mes-cours"]', { navigate: true });
        },
    },
    'courses.new': {
        account: 'active', path: '/mes-cours/nouvelle',
        async act(s) {
            await s.wait(400);
            await s.circleAny(['main form', 'main .card']);
        },
    },
    'courses.add': {
        account: 'active', path: '/mes-cours',
        async act(s) {
            await s.wait(300);
            await s.arrowText('Ajouter une leçon');
            await s.wait(1100);
            await s.clear();
            await s.tapText('Ajouter une leçon');
        },
    },
    'courses.part': {
        account: 'active', path: '/mes-cours/nouvelle',
        async act(s) {
            await s.scrollText(['PDF', '50 pages'], 1000);
        },
    },
    'courses.lesson': {
        account: 'active', path: '/mes-cours',
        async act(s) {
            await s.wait(300);
            await s.tapText('Le présent simple', { navigate: true });
            await s.wait(800);
            await s.circleAny(['#travailler', 'main .card']);
        },
    },
    'courses.time': {
        account: 'active', path: '/mes-cours',
        async act(s) {
            await s.wait(300);
            await s.scrollText(['Il vous reste'], 900);
        },
    },
    'courses.review': {
        account: 'active', path: '/mes-cours',
        async act(s) {
            await s.tapText('Le présent simple', { navigate: true });
            await s.wait(500);
            await s.scroll('#documents', 1000, 120);
            await s.circle('#documents');
        },
    },
    'courses.only': {
        account: 'active', path: '/mes-cours/nouvelle',
        async act(s) {
            await s.scrollText(['Uniquement', 'uniquement'], 900);
        },
    },
    'help.whatsapp': {
        account: 'active', path: '/contact',
        async act(s) {
            await s.wait(400);
            await s.circle('.btn-whatsapp');
        },
    },
    'install.page': {
        account: 'active', path: '/installer-l-application',
        async act(s) {
            await s.wait(400);
            await s.circleAny(['main h1']);
        },
    },
};

/** Scène connue ? (un script qui en nomme une inconnue est refusé avant de filmer) */
export function scene(name) {
    const found = SCENES[name];

    if (!found) {
        throw new Error(`Scène inconnue : ${name}`);
    }

    return found;
}
