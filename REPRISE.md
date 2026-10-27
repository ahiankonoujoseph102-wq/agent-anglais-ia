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

- PR 1 (coût), branche `claude/sweet-johnson-e6lsds` : comptage des jetons par nature (`public/js/token-usage.js`, colonnes `input_text_tokens`…), coût au tarif de chaque nature (`UsageCost::fromSums`), fiche du site par conversation (`SiteGuide::text($kind)`), fenêtre de contexte bornée (`TeacherSetup::contextWindow`), alerte de coût par abonné (`CostWatch`, `usage:alert-costs`, « Réglages »), note `docs/cout-et-marge.md`, CLAUDE.md à jour. Tests verts en local.

## Constats de l'export pour les PR 2 et 3 (chiffres seulement)

- Écho / deuxième écran : environ 9 répliques « élève » qui sont en fait la voix de Teacher Joe, dans 4 conversations ; dans 3 d'entre elles, DEUX accueils dans la même évaluation : la séance était ouverte deux fois (deux onglets ou deux appareils), les deux Teacher Joe se parlaient. Cause aussi possible : chez Gemini, la porte du micro se rouvre dès que le son reçu est épuisé (trou du réseau), même si la réponse continue.
- Réponses coupées : environ 30 répliques du professeur sans ponctuation finale (interrompues par un bruit ou l'écho).
- Mauvaise langue : environ 117 répliques d'élève reconnues en espagnol, portugais ou allemand et 12 en écriture non latine (hindi, coréen, japonais). `inputAudioTranscription.languageCodes` existe (BCP-47) : régler `en-US`, `fr-FR`.
- Délais : 53 réponses sur 1 350 au-delà de 5 secondes (médiane 1,8 seconde) ; plus fréquent au tout début (6,5 %) ; ne grandit pas avec la longueur de la séance.
- Durée annoncée : la séance du jour annonce le temps RESTANT du jour (juste), mais la leçon se termine souvent bien avant (bilan commencé trop tôt) : 15 minutes annoncées, 5 à 8 réelles.
- Évaluations : 20 sur 115 de moins de 2 minutes, 16 sans aucune parole de l'élève, 58 avec un niveau donné en 3 minutes ou moins (verdict trop tôt).
- Pédagogie : félicite une réponse incompréhensible (environ 30 fois), invente la réponse de l'élève (un mot mal reconnu devient un nom de ville), répète la même correction sans expliquer la règle, récite sa présentation complète à une question mal comprise, refuse de répondre en anglais pendant le moment des questions en citant ses consignes, lit à voix haute des marques techniques (balises de silence, mise en forme des consignes).

- PR 1 fusionnée (#109).
- PR 2 (erreurs techniques) sur la branche : `heldVoiceEnd`, `LivePage`, langues de transcription, `cleanSpeech`, `LiveEarlyEnd`, durée annoncée, aide au premier tour. CLAUDE.md à jour (« Erreurs techniques des vraies conversations »).
- PR 2 fusionnée (#110).
- PR 3 (pédagogie, prospects, banc d'essai, Confidentialité) sur la branche ; CLAUDE.md à jour.

## Prochaine étape exacte

Ouvrir la PR 3, vérification GitHub verte, fusionner. Puis le rapport final en français (coût et marge : `docs/cout-et-marge.md` ; erreurs et exemples avant/après : constats ci-dessus, exemples INVENTÉS ; gestes : variables Laravel Cloud, `php artisan migrate` par le déploiement, `php artisan teacher:rehearse --compare` dans « Commands », vérifier le palier Google dans AI Studio).
