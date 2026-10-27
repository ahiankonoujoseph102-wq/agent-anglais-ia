# REPRISE — travail en cours (évaluation personnalisée)

À lire avec `CLAUDE.md` pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais le contenu de l'export des conversations ni aucune clé (l'export reste hors du dépôt : seuls des exemples INVENTÉS vont dans le code et les tests).

Les chantiers précédents (jusqu'à la pédagogie des meilleurs centres, pull request #112) sont fusionnés ; leur bilan est dans `CLAUDE.md` et `docs/methode-teacher-joe.md`.

## Branche

`claude/evaluation-personnalisee`, partie de `main` après la pull request #112. Pousser après chaque étape. (La branche `claude/affiche-club-fondateurs` porte les affiches du Club des membres fondateurs ; elle ne doit pas être fusionnée.)

## Demande (résumé)

1. Vérifier si l'évaluation pose les mêmes questions et donne la même mini-leçon à tout le monde, et pourquoi.
2. Questions : structure commune, mais adaptatives (plus faciles ou plus difficiles selon le niveau perçu) et tirées de la personne.
3. Mini-leçon : une ou deux vraies erreurs de la personne, expliquées en français, réutilisées une fois, encouragement sincère ; presque aucune erreur : une tournure plus naturelle du niveau supérieur.
4. Consignes pas plus longues ; taille avant et après.
5. Banc d'essai : trois profils (débutant, intermédiaire, bon niveau) ; tests, Pint, CI, fusion si vert ; CLAUDE.md et REPRISE.md.

## Fait

- Constat et décisions : `docs/methode-teacher-joe.md`, section 10 (export non disponible dans la session : cause vérifiée dans le code).
- `config/teacher.php` → `assessment` (questions adaptatives, aucune correction pendant les questions, mini-leçon personnelle), `assessment_next_step` (mini-leçon personnelle, marqueur `:items`) et `assessment_next_step_phrase` (secours) ; champ `lesson` de `record_assessment_result` (`TeacherSetup::assessment`), `AssessmentResultRequest::lesson()`, `App\Support\Live\AssessmentMiniLesson`, `live-session.js` (envoi de `lesson`, titre du tableau).
- Textes publics : accueil (question fréquente, appel final), « Avant de commencer », fiche du site.
- Taille des consignes de l'évaluation : 39 719 → 38 938 caractères ; déclaration de la fonction 761 → 1 238 ; total 40 480 → 40 176.
- Banc d'essai : six scénarios inventés `evaluation-{debutant,intermediaire,bon-niveau}-{question,lecon}` ; consignes « avant » = `main` après #112.

## Limite

Aucune clé de fournisseur ni export des conversations dans cette session : le banc d'essai n'a pas pu être joué ici. À lancer dans Laravel Cloud (onglet « Commands ») : `php artisan teacher:rehearse --compare --only=evaluation-debutant-question,evaluation-intermediaire-question,evaluation-bon-niveau-question,evaluation-debutant-lecon,evaluation-intermediaire-lecon,evaluation-bon-niveau-lecon --pause=4`.

## Prochaine étape exacte

Pull request ouverte, CI verte, fusion ; puis le propriétaire lance la commande ci-dessus et fait deux évaluations sur son téléphone (avec deux comptes, en répondant différemment) pour vérifier que les questions et la mini-leçon diffèrent.
