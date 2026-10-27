# Coût des séances en direct et marge par formule

Note du 27 octobre 2026. À relire avec la page « Consommation » de l'administration, qui donne désormais les vrais chiffres (jetons par nature, contexte relu par réponse, coût des abonnés).

## 1. Ce qui est facturé aujourd'hui

- Les 122 conversations des 30 derniers jours (115 évaluations, 7 séances du jour) se sont toutes faites chez **Gemini Live** (`gemini-3.8-live`) ; aucune chez OpenAI.
- **Offre gratuite de Google** : tant que le projet Google n'a pas de compte de facturation, l'API Live est « Free of charge » (page des tarifs de Google, relevée le 27 octobre 2026). En contrepartie, Google peut utiliser ces conversations pour améliorer ses produits. Les limites de l'offre gratuite ne sont pas publiées sur la documentation : elles se lisent dans AI Studio (« Projects », colonne du palier, et « Rate limits »).
- **Quand une limite est dépassée** : Google répond 429. Le site met alors Gemini de côté pendant 5 minutes et ouvre la séance chez **OpenAI Realtime** si la clé `OPENAI_API_KEY` est réglée (payant : environ 10 $ par million de jetons audio en entrée, 20 $ en sortie, et seulement 0,30 $ pour l'audio déjà en cache). Sans clé OpenAI, l'élève voit « Teacher Joe n'est pas joignable pour le moment » et le bouton WhatsApp.
- **Attention** : dès qu'un compte de facturation est lié au projet Google (palier « Tier 1 »), l'usage passe au tarif payant. Le coût affiché dans « Consommation » est toujours celui du tarif payant, même si rien n'est facturé.

## 2. Pourquoi 0,12 à 0,20 dollar par minute

1. **Le contexte est refacturé à chaque réponse.** L'API Live relit et facture, à chaque réponse de Teacher Joe, tout le contexte : les consignes et tout l'audio déjà échangé (documentation de Google Cloud sur la tarification Live ; confirmé sur le forum des développeurs Gemini). Une séance a 3,5 à 5 réponses par minute.
2. **Des consignes très longues** : environ 42 000 caractères pour l'évaluation, 58 000 pour une séance du jour (dont 21 000 pour la fiche du site), soit environ 10 000 à 14 000 jetons relus à chaque réponse.
3. **L'audio s'accumule** : environ 32 jetons par seconde d'échange, gardés dans le contexte. Une séance de 30 minutes finit avec environ 55 000 jetons d'audio relus à chaque réponse.
4. **L'estimation affichée était trop haute** : tous les jetons d'entrée étaient comptés au tarif audio (3 $ par million) alors que les consignes sont du texte (0,75 $). Corrigé : chaque nature de jeton a son tarif.
5. Pas de mise en cache des consignes chez Gemini Live (aucune prise en charge connue). OpenAI, lui, facture le contexte déjà vu au tarif « en cache » (0,06 $ le million pour le texte, 0,30 $ pour l'audio).

## 3. Ce qui a été fait

- Comptage des jetons par nature (texte, audio, cache), nombre de réponses et plus grand contexte d'une réponse, pour chaque séance (« Consommation »).
- Fiche du site réduite aux parties utiles à chaque conversation (séance du jour : 9 400 caractères au lieu de 21 000).
- Séance du jour et « Mes cours » : l'audio gardé dans le contexte est borné à environ 8 minutes d'échange (`GEMINI_CONTEXT_AUDIO_TOKENS`, 12 000 jetons) ; l'évaluation et le bilan de niveau gardent tout (leur verdict dépend de toute la conversation).
- Alerte de coût par abonné (seuil réglable dans « Réglages »).

## 4. Estimations (tarif payant, 600 francs CFA pour un dollar)

Hypothèses : 32 jetons audio par seconde d'échange, 5,1 réponses par minute (séances de l'export), Teacher Joe parle la moitié du temps ; une séance par jour. OpenAI : 600 jetons par minute de parole de l'élève, 1 200 par minute de voix de Teacher Joe, contexte relu au tarif en cache. **Ce sont des estimations** : les chiffres réels arrivent dans « Consommation » dès les premières séances après la mise à jour.

| Séance | Gemini, avant | Gemini, après | OpenAI (cache) |
|---|---|---|---|
| 15 minutes | 4,25 $ | 2,97 $ | 0,43 $ |
| 30 minutes | 15,20 $ | 6,54 $ | 1,17 $ |
| Évaluation de 5 minutes | 0,46 $ | 0,46 $ | 0,10 $ |

Coût par mois (Gemini après optimisation / OpenAI), en francs CFA :

| Formule (prix) | Utilisation faible (20 %) | Moyenne (50 %) | Totale (100 %) |
|---|---|---|---|
| Semaine (2 000) | 1 225 / 257 | 5 067 / 741 | 12 468 / 1 802 |
| Essentiel (7 500) | 5 249 / 1 099 | 21 715 / 3 174 | 53 435 / 7 725 |
| Intensif (14 000) | 15 847 / 2 454 | 53 435 / 7 725 | 117 668 / 21 111 |

## 5. Recommandation

- **Avec Gemini Live au tarif payant, aucune formule n'est rentable** dès une utilisation moyenne : le prix ne peut pas compenser un coût de 0,13 à 0,20 dollar par minute.
- **Tant que l'offre gratuite de Google suffit**, le coût réel est nul : surveiller les limites dans AI Studio.
- **Pour le tarif payant**, OpenAI Realtime (`gpt-realtime-2.1-mini`) devrait coûter environ 5 fois moins grâce au cache. Essayer `LIVE_PROVIDERS=openai,gemini` une semaine, comparer la qualité (écoute, voix, pédagogie) et le coût réel par minute dans « Consommation », puis décider.
- **Prix et durées** (à décider après une ou deux semaines de mesures réelles) : avec OpenAI, Semaine et Essentiel restent rentables jusqu'à une utilisation moyenne et à peu près à l'équilibre en utilisation totale ; Intensif (30 minutes par jour) est à perte en utilisation totale (environ 21 000 francs CFA de coût pour 14 000). Pistes : Intensif à 20 minutes par jour, ou autour de 20 000 francs CFA. Les prix n'ont pas été changés.
