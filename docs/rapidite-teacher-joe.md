# Rapidité de Teacher Joe (fin octobre 2026)

Des abonnés payants signalent que Teacher Joe répond lentement, parfois pas du tout, et parfois s'arrête au milieu d'une phrase puis reste longtemps silencieux. La pédagogie leur plaît. Ce document donne le diagnostic, les corrections faites, et ce qu'il reste à vérifier sur le site en ligne.

## 1. Ce qui a pu être mesuré, et ce qui ne l'a pas été

- **Pas d'accès aux données de production** depuis l'environnement de développement : ni les journaux de Laravel Cloud, ni la base (page « Consommation »). Les chiffres réels sont à lire sur le site (voir la partie 5).
- Mesuré ici : le code du navigateur et du serveur, les réglages, la taille réelle des consignes envoyées à Teacher Joe (avant et après chaque pull request), et trois pannes rejouées dans Chromium avec un faux fournisseur (WebSocket simulé, micro simulé).
- Source extérieure : sur le forum officiel des développeurs Gemini, un développeur mesure des **« blocages silencieux » de Gemini Live de 8 à 51 secondes**, connexion ouverte, sans erreur ni annonce de fin, puis la réponse arrive d'un coup ; il les constate sur l'offre gratuite ET sur une offre payante (discuss.ai.google.dev, sujet 180489, lu le 28 octobre 2026). Il note aussi qu'une session ouverte juste après la fermeture d'une autre peut mettre 10 à 12 secondes à donner sa première réponse.

## 2. Causes, de la plus probable à la moins probable

1. **Renouvellement de la connexion en pleine phrase** (certain dans le code). Le jeton Gemini dure 5 minutes (`GEMINI_TOKEN_MINUTES`) : le navigateur ouvrait une nouvelle connexion 15 secondes avant la fin et fermait l'ancienne AUSSITÔT, même si Teacher Joe parlait ou préparait sa réponse. La fin de sa phrase était perdue et la nouvelle connexion pouvait tarder à répondre : exactement « il s'arrête au milieu, puis silence ». Fréquence : 3 fois par séance de 15 minutes, 6 fois par séance Intensif de 30 minutes, 1 fois dans une évaluation de plus de 5 minutes (souvent au moment du niveau ou de la mini-leçon), 1 à 2 fois dans « Mes cours ».
2. **Blocages silencieux du fournisseur, sans aucune reprise automatique** (très probable, prouvé ailleurs, voir la partie 1). Avant : après 8 secondes, « La connexion est un peu lente » ; après 20 secondes, un bouton « Relancer » à toucher. Rien d'automatique ; la réponse coupée en plein milieu n'était pas détectée du tout.
3. **Trop peu de reconnexions permises** (certain dans le code). 8 connexions au plus par séance : une séance Intensif de 30 minutes en utilisait déjà 7 pour ses renouvellements prévus ; à la première coupure, « Trop de reconnexions pour cette séance ». Et seulement 2 reconnexions automatiques par séance après une coupure (1,5 seconde d'attente chacune), puis le bouton.
4. **Attente de la fin de parole** (certain, effet faible) : 800 millisecondes de silence (1 000 en A1 et A2) avant que Gemini réponde, et 2 secondes avant la fin de tour de secours quand le fournisseur ne voit pas la fin (bruit de fond).
5. **Offre gratuite de Gemini** (possible, NON prouvé). Google ne publie aucune limite chiffrée pour l'API Live (ni sessions simultanées, ni jetons par minute ; la page des limites renvoie à AI Studio). Une limite dépassée donne un 429 : le site passe alors chez OpenAI, mais seulement au DÉBUT d'une séance ou si Gemini refuse une reconnexion. À vérifier sur le site (partie 5). Les blocages silencieux existent aussi en offre payante : payer seul ne les supprimerait pas.
6. **Longueur des consignes** (effet faible). Environ 10 000 jetons, relus à chaque réponse. Mesure (caractères, séance du jour A1) : avant #112 46 958 ; après #112 39 313 (−16 %) ; #113 sans effet sur la séance du jour (évaluation 38 789 → 38 016) ; #118 (ambassadeurs) 41 010 (+4 %) ; maintenant 40 404. Rien n'a grossi de façon notable ces derniers jours : ce n'est pas la cause des plaintes.
7. **Téléphone** (peu probable). La voix est programmée dans le moteur audio (réserve de 120 à 300 millisecondes), indépendante de l'image ; le professeur 3D passe tout seul au visage simple sous 12 images par seconde. Un téléphone très lent peut retarder un peu la voix : « Affichage plus léger » reste proposé.

**Comparaison** : séance du jour et « Mes cours » ont la même mécanique, avec un contexte borné à environ 8 minutes d'audio ; l'évaluation garde tout le contexte (8 minutes au plus). La séance du jour (15 ou 30 minutes) subissait le plus de renouvellements, donc le plus de coupures en pleine phrase.

## 3. Corrections faites (aucun changement de pédagogie, de prix ni de l'enregistrement des séances)

- **Renouvellement au bon moment** (`live-session.js`) : préparé une minute avant la fin du jeton (`platform.live.renew_lead_seconds`), et l'échange de connexion n'a lieu qu'à un moment calme (ni voix de Teacher Joe, ni réponse en cours, ni élève qui parle ou attend une réponse ; `quietMoment` de `turn-watch.js`), au plus tard 4 secondes avant la fin de l'ancienne. Annonce de fin de Google (« goAway ») : même chose, 12 secondes au plus.
- **Reprise automatique et invisible** (`AnswerRecovery`, `turn-watch.js`) quand Teacher Joe ne répond pas, après une parole de l'élève d'au moins 0,25 seconde : relance 6 secondes après la fin de sa parole (`auto_nudge_seconds`), nouvelle connexion chez le même fournisseur 8 secondes plus tard (`auto_reconnect_seconds`, la conversation continue), puis la séance continue chez l'autre fournisseur 10 secondes plus tard (`auto_switch_seconds`, une fois par séance ; nouveau paramètre `unresponsive` de `/connexion`, `LiveConnection::request`). L'élève voit seulement « Un instant… », puis le message de connexion lente ; le bouton « Relancer » reste le dernier recours.
- **Réponse coupée en plein milieu** : plus aucun son reçu depuis 6 secondes (`answer_stall_seconds`) alors que la réponse n'est pas finie : nouvelle connexion tout de suite, et l'indication `teacher.cues.stalled` lui fait finir sa phrase en une phrase courte.
- **Reconnexions** : 4 reconnexions automatiques après une coupure (au bout de 0,3, 1,5, 3 et 5 secondes), compte remis à zéro après une minute sans coupure ; connexions permises par séance = au moins 8, et pour une longue séance un renouvellement par jeton plus 6 (`IsLiveConversation::maxConnections` : 14 pour 30 minutes).
- **Fin de parole** : 900 millisecondes en A1 et A2 (au lieu de 1 000), 700 ailleurs (au lieu de 800), fin de tour de secours à 1,5 seconde (au lieu de 2). Réglables sans toucher au code : `LIVE_BEGINNER_SILENCE_MS`, `LIVE_SILENCE_MS`.
- **Consignes** : partie « Programme d'ambassadeurs » de la fiche du site raccourcie d'un tiers, tous les faits gardés (−600 caractères par conversation).
- **Mesure permanente** : « Consommation », partie « Rapidité de Teacher Joe, jour par jour » (`#rapidite`, `App\Support\Live\Responsiveness`) : par jour (heure de Lomé), nombre de réponses, moyenne, médiane, 9 sur 10, la plus lente, réponses de plus de 5 secondes, et incidents (coupures et reconnexions, erreurs du fournisseur, attentes sans réponse, relances et nouvelles connexions automatiques, passages chez l'autre fournisseur) ; puis moyenne et pire cas par type de séance sur 7 jours.

Vérifié dans Chromium (390 pixels, faux fournisseur) : (1) aucune réponse : relance à 6 secondes, nouvelle connexion 8 secondes plus tard, réponse aussitôt ; (2) réponse arrêtée en plein milieu : nouvelle connexion 6 secondes après, il finit sa phrase ; (3) renouvellement : la nouvelle connexion, prête pendant une phrase de 6 secondes, n'a pris le relais qu'à la fin de la phrase, et une autre a attendu la fin de la réponse à l'élève.

## 4. Offre gratuite : coûts si l'on change (aucun changement fait sans accord)

D'après `docs/cout-et-marge.md` (tarifs relevés le 27 octobre 2026 ; Gemini 3.8 Live payant : audio 3 $ en entrée, 12 $ en sortie par million de jetons ; OpenAI `gpt-realtime-2.1-mini` avec cache), 600 francs CFA pour un dollar :

| | Gemini payant | OpenAI par défaut |
|---|---|---|
| Séance de 15 minutes | environ 2,97 $ (1 780 FCFA) | environ 0,43 $ (260 FCFA) |
| Séance de 30 minutes | environ 6,54 $ (3 920 FCFA) | environ 1,17 $ (700 FCFA) |
| Essentiel (7 500 FCFA), utilisation moyenne, par mois | environ 21 700 FCFA | environ 3 200 FCFA |

Passer Gemini au palier payant se fait dans Google AI Studio en ajoutant un moyen de paiement au projet (passage immédiat d'après Google) ; mais avec Gemini payant, aucune formule n'est rentable. Si la cause est l'offre gratuite, l'option réaliste est **OpenAI par défaut** (`LIVE_PROVIDERS=openai,gemini` dans Laravel Cloud), à essayer une semaine en comparant la rapidité dans la nouvelle partie « Rapidité ». Ce choix vous revient.

## 5. À regarder sur le site après la mise en ligne

1. « Consommation », « Rapidité de Teacher Joe, jour par jour » : la moyenne, la plus lente et les incidents de chaque jour ; comparer les jours avant et après la mise à jour (les mesures de délai existent depuis octobre).
2. « Diagnostic des fournisseurs », partie « Séances en direct », « Dernières erreurs » : des lignes « 429 », « RESOURCE_EXHAUSTED » ou « quota » montrent une limite de l'offre gratuite ; « Aucune réponse » montre les blocages silencieux repris chez l'autre fournisseur.
3. « Consommation », « Séances en direct par fournisseur » : une part importante de séances chez OpenAI signifie que Gemini refuse souvent au début (limite).
