# REPRISE — travail en cours (pédagogie des meilleurs centres)

À lire avec `CLAUDE.md` pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais le contenu de l'export des conversations ni aucune clé (l'export reste hors du dépôt : seuls des exemples INVENTÉS vont dans le code et les tests).

Les chantiers précédents (coût des séances, erreurs techniques, conseiller honnête : pull requests #108 à #111) sont fusionnés ; leur bilan est dans `CLAUDE.md` et `docs/cout-et-marge.md`.

## Branche

`claude/teacher-joe-pedagogie`, partie de `main` après la pull request #111. Pousser après chaque étape.

## Demande (résumé)

1. Recherche : meilleurs centres (British Council, Berlitz, Wall Street English), méthodes (approche communicative, tâches, correction, vérification de la compréhension, révision espacée), applications (Speak, Praktika, Duolingo Max), tuteurs (Cambly, italki, Preply), public d'Afrique francophone. Garder le meilleur, rejeter le médiocre, justifier.
2. Comparer avec ce que fait Teacher Joe (consignes et constats de l'export anonymisé).
3. Avis d'une apprenante, à juger (garder, garder en partie, rejeter) : a) plusieurs raisons d'apprendre l'anglais ; b) moins répéter, plus de questions ouvertes, parler librement puis être corrigée avec des explications.
4. Améliorer Teacher Joe (vrai professeur chaleureux, élève en difficulté : français, rassurer, expliquer, astuce, retour à l'anglais ; sens, moment d'emploi et différence de chaque phrase ; vérifier par des questions, jamais « tu as compris ? » ; partir de ce que l'élève sait ; équilibre répétition et parole libre selon le niveau).
5. Coût : consignes pas plus longues, plus courtes si possible ; taille avant et après.
6. Vérification : `teacher:rehearse` sur A1, A2, B1 ; trois exemples avant et après ; tests, Pint, CI, fusion si vert ; CLAUDE.md et REPRISE.md.

## Fait

- Étapes 1 à 3 : `docs/methode-teacher-joe.md`, section 9 (sources, comparaison, verdicts).
- Étapes 4 et 5 : `config/teacher.php` réécrit (`persona`, `pace`, `coaching` devenu « HOW YOU TEACH », `correction`, `listening`, `lesson`) ; `config/program.php` → `lesson_shares` (A1 et A2 30/25/40, B1 25/20/50). Taille des consignes (caractères, sans le contexte de la leçon) : évaluation 43 277 → 39 638 ; séance du jour A1 43 631 → 35 685 ; séance du jour B1 43 388 → 35 442 ; « Mes cours » A2 43 528 → 37 712 ; bilan de niveau A2 29 916 → 25 621.
- Objectifs : un principal et jusqu'à deux autres (`programs.other_goals`, `Program::otherGoals()`, `ProgramBuilder::otherGoals()`), nouvel objectif `media` « Comprendre les films, les séries et les chansons ».
- Banc d'essai : `resources/teacher/rehearsal/previous.php` = consignes de `main` après #111 ; sept scénarios inventés de plus (A1, A2, B1).

## Limite

Aucune clé de fournisseur dans cette session : le banc d'essai n'a pas pu être joué ici. À lancer dans Laravel Cloud (onglet « Commands ») : `php artisan teacher:rehearse --compare --pause=4`.

## Prochaine étape exacte

Pull request ouverte, CI verte, fusion ; ensuite, le propriétaire lance `php artisan teacher:rehearse --compare` et essaie une séance du jour sur son téléphone (voir le compte rendu de la pull request). Quand la comparaison ne sert plus, supprimer `resources/teacher/rehearsal/previous.php` et l'option `--compare`.
