# REPRISE — travail en cours (coût, erreurs, pédagogie de Teacher Joe)

À lire avec `CLAUDE.md` pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais le contenu de l'export des conversations ni aucune clé (l'export reste hors du dépôt : seuls des exemples INVENTÉS vont dans le code et les tests).

## Branche

`claude/sweet-johnson-e6lsds`, partie de `main` après la pull request #108 (commit c17a1d1). Pousser après chaque étape.

## Demande (résumé)

1. **Coût** (priorité) : vérifier ce qui est facturé, marge par formule (utilisation faible, moyenne, totale), cause du coût par minute, optimisations sûres, alerte de coût par abonné (seuil réglable), recommandation chiffrée sur les prix (ne PAS changer les prix).
2. **Erreurs techniques** vues dans l'export : écho (Teacher Joe se répond à lui-même), réponses coupées, reconnaissance dans la mauvaise langue (hindi, espagnol, portugais, coréen), pics de délai, durée de leçon annoncée incohérente, évaluations courtes et abandons.
3. **Pédagogie** : jamais féliciter une réponse fausse ou incompréhensible, expliquer la règle après deux fois la même erreur, s'adapter à « je ne comprends pas », répétition avec un but, une question à la fois, encouragements vrais, réponses courtes.
4. **Questions des prospects** : prix, frais, temps pour bien parler, disponibilité à toute heure, jour manqué, mauvaise connexion, hors sujet, « êtes-vous une vraie personne ? ».
5. **Mesure** : scénarios inventés dans `teacher:rehearse`, phrase de Confidentialité sur les extraits anonymisés.
6. Livraison : une pull request par priorité, CI verte, fusion ; rapport final en français.

## Constats (étape 1, analyse)

- Toutes les conversations de l'export sont chez Gemini Live (`gemini-3.8-live`). Aucune chez OpenAI.
- **Cause du coût par minute** : l'API Live refacture TOUT le contexte (consignes + audio déjà échangé) à CHAQUE tour (documentation Google Cloud sur la tarification Live, confirmé par le forum des développeurs). Les consignes font environ 42 000 caractères pour l'évaluation (dont environ 21 000 pour la fiche du site `resources/teacher/site-guide.md`), 58 000 pour une séance : environ 10 000 à 14 000 jetons répétés à chaque réponse de Teacher Joe (3,5 à 5 réponses par minute).
- **Estimation trop haute** dans « Consommation » : tous les jetons d'entrée sont comptés au tarif AUDIO (3 $ par million) alors que les consignes sont du TEXTE (0,75 $ par million pour Gemini 3.8 Live ; sortie : texte 4,50 $, audio 12 $ ; tarif relevé le 27 octobre 2026). `usageMetadata.promptTokensDetails` donne la répartition par modalité : à enregistrer.
- Pas de mise en cache des consignes possible dans l'API Live (aucune prise en charge connue).
- `inputAudioTranscription.languageCodes` existe (codes BCP-47) : à régler sur anglais et français.

## Plan

- PR 1 (coût) : répartition texte / audio des jetons (colonnes), tarifs par modalité, fiche du site plus courte pour les conversations, compression du contexte bornée, alerte de coût par abonné (seuil réglable), tests, CLAUDE.md.
- PR 2 (erreurs techniques).
- PR 3 (pédagogie, prospects, banc d'essai, Confidentialité).

## Fait

- (rien de poussé encore)

## Prochaine étape exacte

Mesurer les consignes, écrire la PR 1 (voir Plan).
