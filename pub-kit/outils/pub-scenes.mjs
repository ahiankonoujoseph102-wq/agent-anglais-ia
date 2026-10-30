// Passages de la PUBLICITÉ de 28 secondes (kit pub-kit/) : site de DÉMONSTRATION, comptes fictifs, faux fournisseur.
// Même aide « s » que le catalogue des écrans (record.mjs) : doigt au toucher, défilements doux, sous-titres poussés.
const sleep = (ms) => new Promise((ok) => setTimeout(ok, Math.max(0, ms)));
const say = (s, teacher, seconds) => s.say({ teacher, seconds: seconds ?? Math.max(1.6, teacher.split(/\s+/).length / 2.4) });
const answer = (s, learner) => s.say({ learner });

export function pubPassages() {
    return [
        {
            key: 'ecran-accueil-pastille-rose', seconds: 9, account: 'visitor', path: '/',
            async act(s) {
                await s.wait(2200);
                await s.tap('.hero-alt', { click: false });
                await s.wait(1600);
                await s.tap('.hero-alt');
                await s.wait(4000);
            },
            note: 'Accueil : Teacher Joe en haut, pastille « ou Teacher Rose, au choix » touchée, puis les deux professeurs.',
        },
        {
            key: 'ecran-bouton-evaluation-gratuite', seconds: 7, account: 'visitor', path: '/?pub=bouton',
            async act(s) {
                await s.wait(2500);
                await s.tap('.hero-cta', { navigate: true });
                await s.wait(3000);
            },
            note: 'Accueil : le doigt touche « Passer mon évaluation gratuite », la page « Créer mon compte gratuit » s\'ouvre.',
        },
        {
            key: 'ecran-seance-correction-douce', seconds: 15, account: 'active', path: '/seance?pub=joe', live: 'lesson', teacher: 'joe',
            async act(s) {
                await s.wait(1200);
                await s.tap('#start');
                await s.wait(3000);
                await say(s, 'Tell me about yesterday. What did you do?', 2.4);
                await s.wait(2400);
                await s.scroll('#subtitles', 900, 330);
                await answer(s, 'Yesterday, I go to the market.');
                await s.wait(900);
                await say(s, 'Good try! We say: I went.', 2.6);
                await s.wait(2700);
                await answer(s, 'Yesterday, I went to the market.');
                await s.wait(700);
                await say(s, 'Perfect! Well done!', 1.8);
            },
            note: 'Séance du jour avec Teacher Joe : l\'élève dit « I go », il corrige avec douceur (« I went »), l\'élève redit juste.',
        },
        {
            key: 'ecran-seance-explication-francais', seconds: 15, account: 'active', path: '/seance?pub=rose', live: 'lesson', teacher: 'rose',
            async act(s) {
                await s.wait(1200);
                await s.tap('#start');
                await s.wait(3000);
                await say(s, 'Now, use the past: I went, I saw, I ate.', 2.4);
                await s.wait(2400);
                await s.scroll('#subtitles', 900, 330);
                await answer(s, 'Je ne comprends pas.');
                await s.wait(900);
                await say(s, 'Pas de souci ! « I went » = le passé de « I go ». Essayez !', 3.6);
                await s.wait(3700);
                await answer(s, 'Yesterday, I went to school.');
                await s.wait(700);
                await say(s, 'Très bien ! Great job!', 1.6);
            },
            note: 'Séance du jour avec Teacher Rose : l\'élève bloque (« Je ne comprends pas »), elle explique en français, l\'élève réussit.',
        },
        {
            key: 'ecran-choix-du-professeur', seconds: 10, account: 'active', path: '/espace', teacher: 'joe',
            async act(s) {
                await s.wait(1500);
                await s.scroll('#professeur', 1600, 80);
                await s.wait(2200);
                await s.tap(s.page.getByText('Choisir Teacher Rose').first(), { navigate: true });
                await s.wait(500);
                await s.scroll('#professeur', 10, 80);
                await s.wait(2500);
            },
            note: 'Mon espace : la carte « Votre professeur », le doigt touche « Choisir Teacher Rose », elle devient la professeure.',
        },
    ];
}
