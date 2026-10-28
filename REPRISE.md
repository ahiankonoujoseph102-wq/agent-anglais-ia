# REPRISE — travail en cours (programme d'ambassadeurs)

À lire avec `CLAUDE.md` (section « Programme d'ambassadeurs ») pour reprendre ce travail dans une autre session. Ce fichier ne contient jamais de clé ni de conversation réelle.

Les chantiers précédents (jusqu'à l'évaluation personnalisée, pull request #113) sont fusionnés.

## Demande et décisions

Programme d'ambassadeurs (10 au plus), une étape à la fois, chacune avec sa pull request, tests, Pint, CI verte et compte rendu en français simple. Décisions du fondateur : voir `CLAUDE.md`, « Programme d'ambassadeurs ». Rapports quotidiens et vidéos : dans le groupe WhatsApp, jamais sur la plateforme ; ne rien changer à la fonction d'enregistrement.

## Étapes

1. **Ambassadeurs, accès gratuit, lien, code et code QR** : fait (pull request #114).
2. **Suivi des inscrits** : fait (pull request #115) : cookie de 30 jours posé par `/a/{code}`, champ « Code ambassadeur » à l'inscription (le code tapé passe avant tout, sinon le premier lien), refus de son propre code, signaux (même appareil, même réseau, empreinte seulement), inscrits (prénom et date) dans l'espace ambassadeur et l'administration, « Confidentialité ».
3. **Commissions** : fait (pull request #116) : 20 % du PREMIER paiement validé, dans les 90 jours suivant l'inscription, créées à la validation ; « Commission payée », annulation avec motif (triche), « Ajouter une commission » à la main ; numéro de l'expéditeur lu sur la capture (signal) ; sommes dues et payées dans les deux espaces.
4. **Page publique « Devenir ambassadeur »** (branche `claude/ambassadeurs-etape-4`, en cours de fusion) : compteur réel « Il reste X places sur N » (places de « Réglages » moins les actifs), formulaire de candidature (compte obligatoire), alerte ; plus de place : « Toutes les places d'ambassadeurs sont prises. », formulaire fermé, « Me prévenir quand une place se libère » (liste d'attente visible dans l'onglet « Ambassadeurs »), lien vers les formules.
5. Finition : lien de l'ambassadeur écrit dans ses vidéos de séance (seulement le texte affiché en bas de la vidéo, rien d'autre), fiche du site de Teacher Joe, pied de page, référencement de la page.

## Prochaine étape exacte

Après la fusion de l'étape 4 : partir de `main` à jour, branche `claude/ambassadeurs-etape-5`, étape 5 (finition : lien de l'ambassadeur dans ses vidéos de séance, fiche du site de Teacher Joe, lien dans le pied de page, page indexée).
