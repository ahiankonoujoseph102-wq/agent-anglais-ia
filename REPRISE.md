# REPRISE — chantier en cours : deuxième professeure, Teacher Rose (étape 2 sur 8)

À lire avec `CLAUDE.md` pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais de clé, ni de conversation réelle, ni de transcription.

#119 et #120 ont été annulées (#121) ; #122 (nouvelle évaluation derrière l'interrupteur, formules seulement à la fin) est fusionnée et en ligne, interrupteur sur « Tout le monde ».



## Teacher Rose, étape 3, visage (29 octobre 2026, branche `claude/rose-visage`)

Modèle 3D repris de `claude/festive-keller-2hkpxl` et retouché (joues légèrement rosées, bas du visage plus clair, lèvres plus colorées) ; Teacher Joe reconstruit identique à l'octet (vérifié avec `cmp`). Captures (3D et visage simple) envoyées au fondateur, gardées HORS du dépôt. `ready` reste à false : à passer à true SEULEMENT après sa validation. Photos d'inspiration : jamais dans le dépôt.
## Vidéo du Club fiable (29 octobre 2026, nouveau compte Claude Code)

- **#137 (fusionnée)** : `tools/video/render.html` ne bloque plus quand une machine trop lente est refusée (passages n° 10, 14 et 18 annulés à 80 minutes).
- **Branche `claude/voix-quota`** : synthèse vocale en peu de demandes (`VoiceSplitter`, environ 2 par vidéo au lieu de 11 à 15), deuxième clé Gemini en secours de toute la synthèse vocale, message d'erreur de « Essai des voix » repris de `claude/festive-keller-2hkpxl`. Reste de la priorité 2 : bouton « Écouter en séance » (pull request suivante).
- **#138 (fusionnée), branche `claude/club-trois-minutes`** : vidéo du Club de 3 minutes (parole visée 150 secondes, silences de répétition allongés si la voix est un peu courte), rendu 396 × 704 à 20 images par seconde, relance automatique d'un passage « Club seulement » après un arrêt anticipé (3 essais). Vidéo du 29 octobre (4 minutes 12) gardée telle quelle : sa voix existe déjà, pas de nouvelle demande de synthèse vocale (quota de 10 par jour chez Gemini).
## Chantier en cours : Teacher Rose, étape 2, séances payantes (29 octobre 2026, branche `claude/festive-keller-2hkpxl`)

Décision du fondateur (message de l'étape 2) : PAS d'interrupteur ni de compte de test ; il ne peut pas tester lui-même (micro de son téléphone en panne), un ami fera les essais réels après la mise en ligne ; tout doit être prouvé par les tests (faux Gemini, faux OpenAI). Conditions : (1) le choix de Teacher Rose n'apparaît qu'avec sa voix ET son visage prêts et validés par lui sur images ; (2) Teacher Joe identique au caractère près (garde-fou) ; (3) évaluation non touchée avant son essai réel ; (4) retour rapide à Teacher Joe seul ; (5) choix dans « Mon espace », « Consommation » et « Essai des voix » par professeur.
- **Fait** : voir CLAUDE.md, « Professeurs », étape 2. `teachers.list.rose.ready` = false : AUCUN apprenant ne la voit. L'administrateur peut déjà choisir ses voix (« Essai des voix », onglet Teacher Rose) et l'entendre en direct (« Diagnostic des fournisseurs », « Essayer 30 secondes avec Teacher Rose »).
- **Retour à Teacher Joe seul** : `ready` à false, ou retirer le bloc `rose` de `config/teachers.php`, puis déployer.
- **Étape 3 (suivante)** : visage 3D de Teacher Rose (variante du script `tools/teacher3d/build-teacher-joe.mjs`, personnage stylisé et ORIGINAL), images envoyées au fondateur ; après son accord : `config/avatar.php` → `rose.file`, puis `ready` à true. Les photos d'inspiration ne doivent JAMAIS entrer dans le dépôt.

## Chantier précédent : Teacher Rose, étape 1, socle invisible (29 octobre 2026, branche `claude/festive-keller-2hkpxl`)

Décision du fondateur : les deux professeurs partout (scénario B complet), avec trois corrections acceptées : choix « à l'abonnement » sur la page de bienvenue (jamais sur la page de paiement), vidéos en alternance, évaluation seulement à l'étape 5 après son essai réel. Plan en 8 étapes : CLAUDE.md, « Professeurs (Teacher Joe et Teacher Rose) ».
- **Étape 1 (cette branche)** : liste des professeurs (`config/teachers.php`, Teacher Joe seul), professeur gardé sur le compte et dans chaque séance, consignes, voix Gemini et OpenAI et fiche du site construites à partir du professeur. Rien ne change pour personne : garde-fou par empreintes enregistré sur `main` AVANT tout changement (premier commit de la branche), puis vérifié après.
- **Visage (étape 3)** : personnage stylisé et ORIGINAL, jamais un portrait. Le fondateur a donné des photos d'inspiration générale : elles ne doivent JAMAIS entrer dans le dépôt (public), ni aucune information personnelle ; il les renverra au moment de l'étape 3.
- **Gestes du fondateur à l'étape 1** : aucun (rien de visible).

## Chantier précédent : vidéos du Club, son, traduction et 3 minutes (29 octobre 2026, branche `claude/club-son-traduction-duree`)

Demande du fondateur après la vidéo 25 (mini-dialogue, 56 secondes) : son trop faible, chaque phrase anglaise traduite et expliquée, 3 minutes au moins.
- **Son** (toutes les vidéos de leçon, promotion comprise) : journal GitHub de la vidéo 25 : −13,7 LUFS mais crête +1,4 dB (vidéo 19 de promotion : −14, crête −0,4). Cause : le limiteur travaillait à 24 kHz (fréquence de la voix), puis passage à 44,1 kHz et compression AAC : les crêtes entre deux échantillons dépassaient 0 dB (son écrêté, que le téléphone ou WhatsApp rabaisse et qui grésille). Corrigé : passage à 48 kHz AVANT le limiteur (plafond 0,708, soit −3 dB), son en 48 kHz, puis mesure de la vidéo encodée et, hors de −14 ± 1 LUFS ou crête au-dessus de −1 dB, le son seul est refait (trois fois au plus, quelques secondes). Essai local : −14,0 LUFS, crête −2,2 dB.
- **Script** : chaque segment donne `say` (français), `english`, `translation`, `explain`, `repeat` ; le CODE assemble « phrase anglaise. En français : traduction. explication. Répétez après moi : phrase » et ajoute 2,5 secondes de silence pour répéter ; une phrase sans traduction ou sans explication fait refuser le script ; au moins 4 phrases traduites et 3 à répéter (samedi et vendredi : 3 et 2) ; rappel final demandé.
- **Durée** : visée 210 secondes, minimum 180 : script refusé sous 378 mots, et une voix de moins de 180 secondes fait écrire un nouveau script.
- **Fabrication** : travail GitHub séparé pour le Club (80 minutes, 72 accordées), rendu allégé (432 × 768, 20 images par seconde : environ 70 % du temps, mesuré en local) ; après 250 images, si la fin prévue dépasse le temps accordé, la vidéo est rendue à Talkami sans échec compté (`POST /api/videos/{id}/reporter`) et reprise au passage suivant. Travail « promotion » inchangé (50 minutes, 42 accordées).
- **Vidéo d'aujourd'hui** : migration `rewrite_short_club_videos` (vidéos du Club d'aujourd'hui ou plus tard de moins de 180 secondes : nouveau script, nouvelle voix). « Refaire la vidéo (nouveau script) » sur une vidéo du Club fait de même.

## Chantier précédent : deuxième clé Gemini (29 octobre 2026, branche `claude/deuxieme-cle-gemini`)

Fournisseur `gemini2` des séances en direct, variable `GEMINI_API_KEY_2`, ordre par défaut `gemini,openai,gemini2` ; clé absente : ignorée ; invalide : mise de côté 5 minutes. Offre payante de Google écartée (décision du fondateur). Voir CLAUDE.md. Gestes du propriétaire : créer la clé dans Google AI Studio avec le deuxième compte (nouveau projet, aucun moyen de paiement), la coller dans Laravel Cloud (`GEMINI_API_KEY_2`), mettre `LIVE_PROVIDERS` à `gemini,openai,gemini2` si la variable existe, Deploy, puis « Diagnostic des fournisseurs » → « Tester Gemini (deuxième clé) ».

## Chantier précédent : minutes GitHub Actions et vidéo du Club des abonnés (29 octobre 2026)

Dépôt PUBLIC jusqu'au début du mois (minutes gratuites), puis de nouveau privé. Nouveau jeton des vidéos en place (workflow n° 16 réussi : vidéo 19 en 473 secondes de rendu, 155 millisecondes par image).

- **Partie 1 (#131, fusionnée, branche `claude/minutes-actions`)** : tests seulement sur les pull requests ; 2 vidéos de promotion par jour, réglage « Vidéos de promotion par jour » (page « Vidéos ») ; passage limité à 50 minutes, une vidéo à la fois, budget de 42 minutes. Quand le dépôt redevient privé : régler « Vidéos de promotion par jour » sur 1.
- **Partie 2 (branche `claude/video-club`)** : vidéo quotidienne du Club des abonnés (thème selon le jour, anonymat d'au moins 3 personnes, repli honnête du lundi, chiffres globaux du samedi écrits par le code, historique sans répétition de 8 semaines), bloc « Club des abonnés » en haut de la page « Vidéos », seul bloc visible par un assistant. Voir CLAUDE.md, « Vidéo du Club des abonnés » (dont le calcul des minutes).
- **Gestes du propriétaire** : chaque jour, page « Vidéos » → bloc « Club des abonnés » → « Télécharger pour WhatsApp », « Copier le texte », publier dans le groupe, cocher « Publiée ». Au retour en privé : « Vidéos de promotion par jour » sur 1.
- **À proposer plus tard (non codé)** : e-mail ou notification « vidéo du Club prête » ; réponses vocales du vendredi écoutées par l'équipe (rien n'est reçu par le site).

## Chantier précédent : icône du site pour Google (29 octobre 2026, branche `claude/icone-du-site`)

Icônes du site refaites sur fond blanc uni (48, 96, 192 pixels et favicon.ico), déclarées sur toutes les pages par `<x-site-icons>`. Google met à jour l'icône à son propre rythme (plusieurs jours à quelques semaines). Voir CLAUDE.md.

## Chantier précédent : audit du référencement (29 octobre 2026, branche `claude/referencement-audit`)

Partie 3 de 3. Compte rendu : `docs/referencement-audit.md`. À faire par le propriétaire : Search Console (vérification, sitemap, demande d'indexation), fiche d'établissement Google, liens depuis TikTok, WhatsApp, Facebook, YouTube.

## Chantier précédent : accueil de la première séance d'une formule (#128, fusionnée) (29 octobre 2026, branche `claude/accueil-premiere-seance`)

Partie 2 de 3. Version légère : une phrase de félicitations et deux phrases sur le déroulé, ajoutées à l'indication de début de la première séance d'une formule payée. Voir CLAUDE.md.

## Chantier précédent : fin de séance professionnelle (#127, fusionnée) (29 octobre 2026, branche `claude/fin-de-seance`)

Partie 1 de 3 (puis accueil de la première séance d'un abonné, puis audit du référencement). Cause trouvée dans le code (aucune conversation réelle lue) : « Mes cours » disait au revoir puis se taisait 90 secondes avant la fin ; séance du jour : au revoir à 2 minutes, silence jusqu'au minuteur si `end_lesson` n'était pas appelé, puis coupure au milieu d'une phrase. Corrigé par `SessionClosing` (annonce à 60 secondes, conclusion à 40, « À demain ! », fermeture propre), d'après le temps restant seulement. Voir CLAUDE.md, feuille de route.

## Chantier précédent : assistants de l'administration (28 octobre 2026, branche `claude/assistants`)

Analyse : l'administration était protégée par UN middleware (`admin`) qui laissait entrer le seul rôle `admin` de la colonne `users.role` ; aucune autre distinction. Ajouté : rôle `assistant` dans ce même système, liste unique des pages permises (`App\Support\Admin\AdminAccess::ASSISTANT_ROUTES`), refus par défaut de tout le reste, menu et boutons filtrés, « Nommer assistant » / « Retirer le rôle d'assistant » sur la fiche d'un inscrit (administrateur principal seul), « Traité par » dans l'historique des paiements. Voir CLAUDE.md, feuille de route.

## Chantier précédent : Club des abonnés (#125, fusionnée) (28 octobre 2026, branche `claude/club-abonnes`)

Lien du groupe WhatsApp des abonnés dans « Réglages » (valeur de départ du fondateur), montré seulement aux abonnés payants actifs dans « Mon espace » et sur la page du paiement validé. Voir CLAUDE.md, feuille de route. Ensuite : partie 2, rôle « Assistant » (branche à part).

## Chantier précédent : page « Abonnés payants » (#124, fusionnée) (28 octobre 2026, branche `claude/abonnes-payants`)

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

1. Teacher Rose : étape 2 fusionnée si les tests sont verts ; ensuite étape 3 (visage), sur l'accord du fondateur. L'essai réel ci-dessous reste la condition de l'étape 5.
2. Le fondateur refait une évaluation avec un compte de test (« Réinitialiser l'évaluation » sur sa fiche), en filmant avec « Enregistrer ma séance », et vérifie : bienvenue, nom, « comment ça va » ; aucune coupure sur « Je… » ; plus de longs silences après sa réponse ; aucune coupure vers 5 minutes ; mini-leçon qui attend sa répétition ; niveau dit en français une seule fois ; « Avez-vous une autre question ? » ; jamais « les formules s'affichent ». « Lire la conversation » : plus de « répond en 17,9 secondes » ni de « auth token has expired ».
3. En cas de problème : « Réglages » → « Nouvelle évaluation » → « Désactivée » remet l'ancienne évaluation sans redéploiement (les corrections techniques A, B, C restent pour tout le monde).
4. Lancer le banc d'essai sur Laravel Cloud : `php artisan teacher:rehearse --compare --only=nouvelle-evaluation-debut-de-phrase,nouvelle-evaluation-reponse-fausse,nouvelle-evaluation-question-formules,nouvelle-evaluation-conclusion`.
