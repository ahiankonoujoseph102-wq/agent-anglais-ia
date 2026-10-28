# REPRISE — chantier en cours : évaluation fiable (fin de tour, autorisation, consignes)

À lire avec `CLAUDE.md` pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais de clé, ni de conversation réelle, ni de transcription.

#119 et #120 ont été annulées (#121) ; #122 (nouvelle évaluation derrière l'interrupteur, formules seulement à la fin) est fusionnée et en ligne, interrupteur sur « Tout le monde ».

## Dernier chantier : page « Abonnés payants » (28 octobre 2026, branche `claude/abonnes-payants`)

Rien de tel n'existait (« Inscrits » : recherche et pays ; « Historique » : paiements ; « Inactifs » : abonnés sans séance). Ajouté : onglet « Abonnés payants » (`/admin/abonnes`), voir CLAUDE.md, feuille de route. « Membres fondateurs » : aucune notion à part dans le code ; un accès donné avec « Offrir un accès » (fiche d'un inscrit) apparaît dans la liste « Accès offerts », hors des chiffres des payants. À proposer au fondateur si besoin : une case « Membre fondateur » sur la fiche d'un inscrit pour une liste à part.

## Chantier précédent : évaluation fiable (#123, fusionnée) (28 octobre 2026, branche `claude/dazzling-brahmagupta-hn9p2k`)

Deux vidéos d'essai (« Enregistrer ma séance ») : une vraie cliente (analysée par la session précédente) et la secrétaire du fondateur (vidéo de 5 minutes, transcrite sur la machine de la session, transcription NON gardée). La vidéo commence environ 15 secondes après le début de la séance (le minuteur affiche 4:45).

**Deuxième vidéo, problèmes (moment dans la vidéo → cause probable)** :
- 0:03 accueil sans « bienvenue pour l'évaluation gratuite » → consigne (corrigé).
- 0:45 et 1:57 la phrase « Next time, you can say: Can you say it in French, please? » apprise deux fois → consigne (une seule fois, corrigé).
- 2:24 correction (« You said … We say … ») pendant les questions, puis une nouvelle question dans la même réplique → règles générales de correction plus fortes que l'évaluation (corrigé : « NO CORRECTION at this stage »).
- 2:56 elle dit juste « I went to the beach » ; 3:05 « Say, I went to the beach » (6 secondes après) ; 3:11 fin de tour signalée par le navigateur, micro fermé : 6,5 secondes de silence total ; 3:18 « You said, I went to the beach. We say, I went to the beach. Can you say it again? » (fausse correction d'une phrase juste, troisième répétition) → cause technique A, puis consignes (jamais corriger ni faire répéter une phrase juste ; deux essais au plus ; corrigé).
- 3:30 verdict : 4,7 secondes de silence (appel de la fonction de résultat, connu).
- 3:30 « Your level is A1 » dit seulement en anglais ; à 4:36 elle demande ce que veut dire « son niveau » → niveau annoncé en français avec son nom (« A1 : débutant »), corrigé.
- 3:40 mini-leçon : correction, règle et question ouverte difficile (« Can you tell me something you did yesterday using went? ») dans UNE réplique, sans « Repeat after me » ni attente → elle doit demander l'explication en français → mini-leçon en une action par réplique (corrigé).
- 4:52 « Les formules s'affichent juste sous votre résultat » alors que rien n'est affiché → consigne de l'évaluation ET `PlanFacts::where` (qui disait « after the level announcement, they are already shown », faux depuis #122) ; corrigé.
- 4:59 la vidéo s'arrête pendant qu'il parle : cause non établie (arrêt manuel, ou fin de l'autorisation de 5 minutes, problème C, qui tombe à ce moment de la séance).
- Délais : réponses de Gemini 1,5 à 2,3 secondes en général, 3,2 à 3,4 secondes deux fois ; seules les fins de tour signalées par le navigateur donnent 6 secondes et plus.

**Causes trouvées** :
- **A (Gemini lent ou muet après une fin de tour signalée par le navigateur)** : le navigateur ne la signale que si Gemini n'a pas vu la fin de la parole (bruit de fond pris pour de la voix, voix trop faible). Il fermait alors le micro et envoyait seulement `audioStreamEnd`. Ce message vide l'audio en attente mais n'est pas documenté comme une fin de parole ; la détection de Gemini, privée de tout son, attendait le silence qui ne venait plus, jusqu'à ce que l'élève reparle (réponse en 17,9 secondes, 15:14:36 de la première séance ; 6,5 secondes à 3:11 de la vidéo). Correction : 3 secondes de vrai silence (paquets de zéros) que sa détection reconnaît comme une fin de parole ordinaire, puis `audioStreamEnd` (comme OpenAI, qui reçoit déjà du silence). Aucune indication, aucune relance envoyée au professeur.
- **B (fin de parole signalée au moment où Teacher Joe finit)** : deux causes possibles, toutes deux traitées. (1) La voix de l'apprenant était mesurée dès la fin programmée de la voix du professeur, sans la garde de 400 millisecondes de la porte du micro : la fin de sa voix qui revient par le haut-parleur (annulation d'écho coupée par défaut sur Android) pouvait ouvrir un « tour ». Désormais même garde que la porte (`teacherAudible`). (2) Course ordinaire : la fin de tour de secours part 2 secondes après la parole, alors que Gemini répond normalement en 1,5 à 2,9 secondes ; elle devient sans danger, puisqu'elle n'envoie plus que du silence.
- **C (autorisation expirée en pleine séance, 1011)** : le jeton Gemini durait 5 minutes au plus (`GEMINI_TOKEN_MINUTES`). Après le verdict, le navigateur le renouvelait 15 secondes seulement avant l'expiration ; la création d'un jeton chez Google peut prendre jusqu'à 15 secondes : cause la plus probable, il expirait d'abord (le journal montre l'expiration à 15:15:08, environ 5 minutes après la connexion de 15:10:10). Correction : un jeton pour TOUTE la séance (fin autorisée + 60 secondes ; évaluation : plus la mini-leçon et les questions). Le décompte d'une séance dont le navigateur disparaît garde l'expiration courte d'avant (aucune minute perdue en plus pour un téléphone qui s'éteint). Limite assumée : un navigateur trafiqué qui couperait ses signaux pourrait parler jusqu'à la fin de sa séance sans être décompté au-delà de 5 minutes (avant : 10 minutes au plus). La fermeture annoncée par Google (« goAway », environ 10 minutes) demande toujours un nouveau jeton.

**Consignes de l'évaluation** (`teacher.assessment_new`, plus courte que l'ancienne : 5 551 caractères contre 5 653) : bienvenue pour l'évaluation gratuite, nom avant toute autre question, « How are you today? » ; « Take your time. » sur une phrase commencée (« Je… »), « didn't hear » seulement après un vrai silence ; aucune correction pendant les questions ; jamais « Nice! » sur une réponse fausse ; jamais corriger ou faire répéter une phrase juste ; deux essais au plus, puis explication simple en français ; phrase « Next time, you can say … » une seule fois ; niveau annoncé en français avec son nom, jamais redit dans la conclusion ; « Avez-vous une autre question ? » à la fin de chaque réponse, jamais « une autre question » si aucune n'a été posée ; formules jamais dites affichées avant la fin (« s'afficheront … à la fin de notre échange »). Mini-leçon (`assessment_next_step`, `assessment_next_step_phrase`, communes) : une action par réplique, attente de la répétition, puis « Your turn » facile, puis seulement l'invitation aux questions. Silence d'une seconde (au lieu de 0,8) avant de répondre pendant l'évaluation. Ancienne évaluation : mêmes faits justes (formules, niveau non redit).

**Banc d'essai** : `nouvelle-evaluation-debut-de-phrase`, `-reponse-fausse`, `-question-formules`, `-conclusion` (inventés).

**Propositions NON codées (hors cadre)** :
- Délai du verdict (4,7 secondes de silence) : une courte phrase avant l'appel de la fonction (« Merci, je regarde votre niveau… ») ; à décider.
- Minuteur de silence (toujours en attente de l'accord du fondateur) : voir plus bas.

## Chantier précédent : évaluation corrigée (#122, fusionnée)

Après l'annulation de #119 et #120 (pull request #121), le fondateur a refait l'évaluation : toujours médiocre. Demande : évaluation seulement ; rien dans la séance du jour, « Mes cours », les prix, l'enregistrement des séances ni le choix du fournisseur ; aucune relance automatique basée sur le volume du micro ; consignes courtes.

- **Bug des formules et du silence (corrigé pour tout le monde)** : cause dans `public/js/live-session.js` et `conversation-end.js` : (1) formules affichées dès le niveau montré (`showAssessmentResult`), alors que la conversation continue jusqu'à 3 minutes ; (2) 30 secondes avant la fin, l'indication de conclusion FERMAIT le micro (`endMicStream`) : plus rien n'arrivait à Teacher Joe ; (3) cette indication disait « si vous avez déjà dit la phrase de fin, ne dites rien », et la séance ne se coupait qu'à la phrase de fin exacte reconnue : sinon, séance en direct et muette jusqu'au minuteur. Correction : formules seulement dans `finish()`, micro ouvert jusqu'à la phrase de fin, fin après `platform.assessment.silent_end_seconds` (10) de silence après l'indication, phrase de fin reconnue par ses quatre derniers mots, indication « redites seulement la phrase de fin ».
- **Nouvelle évaluation** (`teacher.assessment_new`, `App\Support\Live\AssessmentVersion`) : accueil humain (bonjour, nom, comment ça va, en attendant chaque réponse), hésitation remarquée par le professeur lui-même d'après ce qu'il entend (reformulation en anglais très simple, puis traduction en français et question reposée), réponse en français ou « je ne comprends pas » : jamais la suite en anglais comme si de rien n'était, « traduis » : traduction puis « Next time, you can say: Can you say it in French, please? », vrai niveau jamais gonflé (« même avant A1 pour l'instant », avec tact). Interrupteur « Réglages » → « Nouvelle évaluation » (`#nouvelle-evaluation`, réglage `assessment.new_version` : `off`, `test` par défaut, `all`) ; case « Compte de test » dans la fiche d'un inscrit (`#compte-de-test`, `users.is_test_account`, route `admin.users.test-account`). Les prospects gardent l'évaluation actuelle tant que « Tout le monde » n'est pas choisi.
- **Minuteur de silence** : NON codé (décision du fondateur). Proposition à lui soumettre : si l'apprenant ne dit rien du tout pendant 12 secondes après une question, une seule indication « reformulez plus simplement » ; ce serait un minuteur sur l'ABSENCE de toute parole reconnue par le fournisseur, pas sur le volume du micro.
- **Banc d'essai** : sept scénarios `nouvelle-evaluation-*` (« après » = nouvelle évaluation, « avant » = actuelle).

## Incident du 28 octobre 2026 : #119 et #120 annulées

Essai réel de l'évaluation en ligne par le fondateur et sa secrétaire : pas de salutation ni de nom au début, niveau trop haut (A2 pour un A1 ou moins), anglais maintenu quand la personne ne comprend pas ou répond en français, Teacher Joe qui parle seul, se perd, devient incohérent, voix qui baisse. Les pull requests #120 (évaluation qui inspire confiance) et #119 (rapidité) ont été annulées par `git revert` (pull request #121) : le code est identique à `main` après #118. Le texte de #119 et #120 reste dans l'historique git (commits 413c45e et b4f524d) ; ne rien en reprendre sans accord.

**Exigences du fondateur pour la suite (rien de codé, AUCUNE mise en ligne de Teacher Joe sans son accord) :**
1. L'évaluation commence par saluer, demander le nom, demander comment ça va, attendre la réponse, puis seulement entrer dans le sujet.
2. Le niveau donné est le vrai, jamais gonflé pour faire plaisir.
3. Si la personne répond en français ou dit qu'elle ne comprend pas, Teacher Joe passe en français.
4. Il reste cohérent et ne parle jamais seul (aucune relance automatique, aucun changement de fournisseur ni de voix en cours d'évaluation sans preuve que c'est sûr).

## Programme d'ambassadeurs

Terminé et fusionné (pull requests #114 à #118). Après la mise en ligne : demander l'indexation de talkami.net/devenir-ambassadeur dans Google Search Console (« Inspection de l'URL »).

## Prochaine étape exacte

1. Fusionner la pull request de ce chantier si les tests sont verts (CI, ou toute la suite et Pint sur la machine de la session si la CI ne démarre pas faute de minutes GitHub Actions).
2. Le fondateur refait une évaluation avec un compte de test (« Réinitialiser l'évaluation » sur sa fiche), en filmant avec « Enregistrer ma séance », et vérifie : bienvenue, nom, « comment ça va » ; aucune coupure sur « Je… » ; plus de longs silences après sa réponse ; aucune coupure vers 5 minutes ; mini-leçon qui attend sa répétition ; niveau dit en français une seule fois ; « Avez-vous une autre question ? » ; jamais « les formules s'affichent ». « Lire la conversation » : plus de « répond en 17,9 secondes » ni de « auth token has expired ».
3. En cas de problème : « Réglages » → « Nouvelle évaluation » → « Désactivée » remet l'ancienne évaluation sans redéploiement (les corrections techniques A, B, C restent pour tout le monde).
4. Lancer le banc d'essai sur Laravel Cloud : `php artisan teacher:rehearse --compare --only=nouvelle-evaluation-debut-de-phrase,nouvelle-evaluation-reponse-fausse,nouvelle-evaluation-question-formules,nouvelle-evaluation-conclusion`.
