# REPRISE — chantier en cours : évaluation (fin sans silence, nouvelle évaluation derrière un interrupteur)

À lire avec `CLAUDE.md` (section « Programme d'ambassadeurs ») pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais de clé ni de conversation réelle.

Les chantiers précédents (jusqu'au programme d'ambassadeurs, pull request #118) sont fusionnés ; #119 et #120 ont été annulées (#121).



## Chantier en cours : évaluation corrigée (28 octobre 2026)

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

1. Fusionner la pull request de ce chantier (branche `claude/amazing-bohr-sizyh1`) seulement avec une CI verte.
2. Le fondateur marque ses comptes d'essai : administration → « Inscrits » → fiche → « Compte de test » (`#compte-de-test`), puis « Réinitialiser l'évaluation » sur ce compte pour la repasser. Laisser « Réglages » → « Nouvelle évaluation » sur « Comptes de test seulement ».
3. Essai réel par le fondateur et sa secrétaire avec ces comptes, en vérifiant les 4 exigences ci-dessus. Passer à « Tout le monde » SEULEMENT avec son accord ; en cas de problème, « Désactivée » remet l'évaluation actuelle sans redéploiement.
4. Minuteur de silence : à soumettre au fondateur, ne rien coder sans son accord.
