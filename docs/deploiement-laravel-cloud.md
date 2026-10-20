# Mettre Talkami en ligne sur Laravel Cloud (depuis un téléphone Android)

Ce guide se suit dans l'ordre, avec le navigateur du téléphone (Chrome). Aucun ordinateur n'est nécessaire.

- **Ce qu'il faut toucher** est écrit en **gras**.
- **Vous devez voir** indique ce qui prouve que l'étape a réussi.
- Les noms des boutons viennent de la documentation officielle de Laravel Cloud, lue le **17 octobre 2026** (<https://laravel.com/cloud/docs>). Si un bouton porte un nom un peu différent, cherchez le plus proche.
- Si une page est trop étroite : dans Chrome, touchez ⋮ puis cochez **Version pour ordinateur**, puis faites défiler à deux doigts.

> **Règle d'or** : une clé, un mot de passe ou un jeton ne se colle **que** dans Laravel Cloud. Jamais dans GitHub, jamais dans un message WhatsApp, jamais dans une capture d'écran envoyée à quelqu'un.

---

## 0. Ce que fait le projet tout seul

Vous n'avez rien à programmer. Le dépôt est déjà prêt :

- les captures de paiement et les attestations vont dans un **bucket privé** (jamais sur le disque du serveur, qui est effacé à chaque déploiement, jamais par un lien public) ;
- les tâches automatiques (fin des abonnements, fermeture des séances abandonnées, effacement des conversations de plus de 90 jours) sont déclarées dans `routes/console.php` : il suffit d'activer **Scheduler** (étape 9) ;
- en production : mode débogage coupé quoi qu'on écrive, cookies sécurisés, HTTPS obligatoire, proxys de Laravel Cloud crus (sinon le site croirait être en http), fuseau Africa/Lome ;
- les données de démonstration (compte « password ») **refusent** de s'installer en production.

---

## 1. Créer le compte Laravel Cloud et choisir l'offre Starter

1. Ouvrez <https://cloud.laravel.com/sign-up>.
2. Créez le compte, puis **choisissez l'offre Starter** et ajoutez une carte bancaire.
3. Vous devez voir : un tableau de bord vide, « Starter » comme offre. Le premier mois (31 jours) est offert, avec 5 $ de crédit d'utilisation.

Réglez tout de suite une limite de dépense (voir la dernière partie) pour qu'aucune mauvaise surprise ne soit possible.

---

## 2. Relier GitHub

1. Dans Laravel Cloud, **Overview**, puis **+ New application**.
2. Touchez **Connect to GitHub** (ou « Continue with GitHub »). Une nouvelle fenêtre GitHub s'ouvre.
3. Connectez-vous avec le compte propriétaire du dépôt (`ahiankonoujoseph102-wq`) et **autorisez l'application « Laravel Cloud » pour le dépôt `agent-anglais-ia`** (choisissez ce dépôt, ou « All repositories »).
4. Vous devez voir : retour sur Laravel Cloud, avec la liste de vos dépôts.

Si le dépôt n'apparaît pas : ouvrez <https://github.com/apps/laravel-cloud-app/installations/select_target>, choisissez votre compte, donnez accès au dépôt, **Save**, puis revenez.

---

## 3. Créer l'application

1. Choisissez le dépôt **agent-anglais-ia**.
2. Nom de l'application : `talkami`.
3. **Région** : choisissez en **Europe** (Francfort `eu-central-1`, Irlande `eu-west-1` ou Londres `eu-west-2`). La documentation ne propose aucune région en Afrique ; l'Europe est la plus proche du Togo. **Ce choix est définitif** : la base de données et le bucket devront être dans la même région. Les prix sont identiques dans les trois régions d'Europe.
4. Touchez **Create Application**.
5. Vous devez voir : la page de l'environnement par défaut (« production »), avec un schéma de l'infrastructure (le *canvas*).

---

## 4. Régler la version de PHP, la construction et le déploiement

Ouvrez les réglages de l'environnement (**Settings**), section **Deployments** (et **General** pour PHP).

1. **PHP** : choisissez **8.3** (la version testée par l'intégration continue du projet).
2. **Build commands** (commandes de construction) : effacez tout ce qui est pré-rempli (le projet n'a pas de JavaScript à compiler : pas de `npm`) et collez :

   ```sh
   composer install --no-dev --optimize-autoloader
   php artisan optimize
   ```

3. **Deploy commands** (commandes de déploiement) : effacez tout et collez :

   ```sh
   php artisan migrate --force
   php artisan app:prepare-voice
   ```

   `--force` évite la question « Voulez-vous vraiment migrer en production ? » : Laravel Cloud ne peut répondre à aucune question. Ne mettez **jamais** `db:seed`, `optimize:clear` ni `storage:link` ici.

   `app:prepare-voice` prépare à l'avance les parties communes des messages vocaux (message de bienvenue, messages du résultat de l'évaluation), pour qu'elles soient prêtes avant la première écoute. Elle prend au plus 2 minutes 30 secondes, ne fait aucune demande quand tout est déjà prêt, passe à OpenAI pour un message que Gemini ne peut pas finir (si `OPENAI_API_KEY` est présente) et **ne fait jamais échouer le déploiement** (en cas de problème chez Google ou OpenAI, elle l'écrit dans le journal du déploiement et s'arrête). Ce qui manque encore est complété chaque heure par le planificateur (étape 9), ou à la main dans l'administration (« Diagnostic de la voix », bouton « Préparer le message de bienvenue »).

4. **Push to deploy** : dans **Settings, Deployments**, vérifiez que l'interrupteur est **activé** et que la **branche** de l'environnement est **`main`**. C'est l'option par défaut : à chaque fois que du code arrive sur `main`, Laravel Cloud construit et déploie tout seul, sans coupure.
5. Touchez **Save** (ne déployez pas encore).

---

## 5. Ajouter la base de données MySQL

1. Sur le canvas de l'environnement, touchez **Add database**.
2. Choisissez **Laravel MySQL**, **créer un nouveau cluster**.
3. Nom du cluster : `talkami-db` ; nom de la base : `talkami` ; taille : **Flex 512 MiB** (la moins chère, suffisante pour démarrer, offre Starter = tailles Flex seulement) ; stockage : **5 Go** (le minimum).
4. **Région** : la même que l'application (Europe).
5. Validez.
6. Vous devez voir : une carte « MySQL » reliée à l'application sur le canvas.

Laravel Cloud ajoute tout seul `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` : **ne les créez pas**. Les sauvegardes quotidiennes sont gardées 7 jours par défaut ; vous pouvez monter à 30 jours (**Organization, Resources, Databases**, votre cluster, **Backups, Settings**).

---

## 6. Ajouter le bucket privé (captures de paiement et attestations)

1. Sur le canvas, touchez **Add bucket**, puis **créer un nouveau bucket** de type **Laravel Object Storage**.
2. Nom : `talkami-fichiers`.
3. **File visibility (visibilité des fichiers) : choisissez Private.** C'est le point le plus important : en privé, aucun fichier n'a d'adresse publique.
4. **Disk name** : `r2` (le nom n'a pas d'importance).
5. Cochez **Set as default disk** (disque par défaut). Laravel Cloud ajoute alors tout seul `FILESYSTEM_DISK` et les clés d'accès `AWS_*` : **ne les créez pas**.
6. Gardez « Jurisdiction : Automatic » (ou **European Union** si vous préférez).
7. Vous devez voir : une carte « Bucket » reliée à l'application.

Le site utilise automatiquement le disque par défaut pour les fichiers privés. La variable `PRIVATE_FILES_DISK` ne sert que si un jour vous voulez un autre disque : **laissez-la absente**.

---

## 7. Créer les variables d'environnement

Dans **Settings, General** (section **Environment variables**), ajoutez les variables du tableau ci-dessous, une par une (**Add variable**), puis **Save**. Laravel Cloud écrit ces valeurs dans le fichier `.env` du serveur : elles ne sont jamais dans le code.

> Après **chaque** modification de variable, il faut **redéployer** (bouton **Deploy**) pour qu'elle soit prise en compte.

### 7.1 À créer à la main

| Nom | Rôle | Exemple (fictif) |
|---|---|---|
| `APP_NAME` | Nom technique de l'application (journaux, cookies). Le nom affiché sur le site vient de `PLATFORM_NAME`. | `Talkami` |
| `APP_ENV` | Environnement. Doit valoir `production`. | `production` |
| `APP_KEY` | Clé de chiffrement de Laravel (voir l'étape 8 pour la générer). **Secrète.** | `base64:EXEMPLE_FICTIF_A_REMPLACER_PAR_VOTRE_CLE=` |
| `APP_DEBUG` | Affiche les erreurs détaillées. Doit valoir `false`. | `false` |
| `APP_URL` | Adresse publique du site, en `https://`. Au début : l'adresse en `laravel.cloud` donnée par Laravel Cloud ; ensuite votre domaine. | `https://www.exemple-talkami.com` |
| `APP_TIMEZONE` | Fuseau horaire des séances, des abonnements et du planificateur. | `Africa/Lome` |
| `APP_LOCALE` | Langue de l'interface. | `fr` |
| `APP_FALLBACK_LOCALE` | Langue de secours. | `en` |
| `DB_CONNECTION` | Type de base. Les autres `DB_*` sont ajoutées par Laravel Cloud. | `mysql` |
| `SESSION_DRIVER` | Où sont gardées les connexions : en base (le disque du serveur est effacé à chaque déploiement). | `database` |
| `SESSION_LIFETIME` | Durée d'une connexion inactive, en minutes. | `120` |
| `SESSION_SECURE_COOKIE` | Cookie envoyé seulement en HTTPS. | `true` |
| `CACHE_STORE` | Où est gardé le cache : en base. | `database` |
| `QUEUE_CONNECTION` | Pas de worker : les tâches s'exécutent tout de suite. | `sync` |
| `LOG_CHANNEL` | Canal des journaux. | `stack` |
| `LOG_STACK` | Envoie les journaux à l'onglet **Logs** de Laravel Cloud. | `laravel-cloud-socket` |
| `LOG_LEVEL` | Niveau de détail des journaux (jamais `debug` en production). | `warning` |
| `BCRYPT_ROUNDS` | Solidité du hachage des mots de passe. | `12` |
| `APP_MAINTENANCE_DRIVER` | Mode maintenance partagé entre les serveurs (recommandé par Laravel Cloud). | `cache` |
| `APP_MAINTENANCE_STORE` | Cache utilisé pour la maintenance. | `database` |
| `GEMINI_API_KEY` | **Clé de l'API Gemini (Google).** Créée sur <https://aistudio.google.com/apikey>. **Se colle uniquement ici, dans Laravel Cloud.** | `AIza-EXEMPLE-FICTIF-NE-PAS-UTILISER` |
| `PLATFORM_NAME` | Nom commercial affiché sur le site. **Doit valoir « Talkami », ou ne pas exister** (la valeur par défaut est « Talkami »). Ne jamais y mettre « Teacher Joe » : c'est le nom du professeur. | `Talkami` |

### 7.2 Facultatives (le projet a déjà une bonne valeur par défaut : créez-les seulement pour changer)

| Nom | Rôle | Valeur par défaut |
|---|---|---|
| `PLATFORM_SHORT_NAME` | Nom court de l'application installée sur l'écran d'accueil du téléphone. | `Talkami` |
| `PHONE_DEFAULT_COUNTRY_CODE` | Indicatif ajouté aux numéros saisis sans indicatif. | `228` |
| `PHONE_LOCAL_LENGTH` | Nombre de chiffres d'un numéro local. | `8` |
| `GEMINI_LIVE_MODEL` | Modèle de la conversation vocale. | `gemini-3.8-live` |
| `GEMINI_API_VERSION` | Version de l'API Gemini Live. | `v1alpha` |
| `GEMINI_TEXT_MODEL` | Modèle texte léger (programme, messages). | `gemini-3.5-flash-lite` |
| `GEMINI_VOICE_NAME` | Voix du professeur par défaut. Facultative : absente, c'est **Achird** (voix masculine). Une voix choisie dans l'administration (« Essai des voix ») la remplace. Si une ancienne variable `GEMINI_VOICE` existe, supprimez-la : elle n'est plus lue. | absente |
| `GEMINI_VOICE_GAIN` | Volume de la voix du professeur (1 = tel quel ; un limiteur évite toute saturation). Facultative. | absente (2) |
| `LIVE_ECHO_CANCELLATION` | Annulation d'écho du navigateur. Facultative. Mettre `false` seulement si, après essai sur téléphone, la voix est trop basse et qu'aucun écho ne revient (voir CLAUDE.md). | absente (true) |
| `GEMINI_TTS_MODEL` | Modèle de synthèse vocale (« Essai des voix », message de bienvenue, message du résultat de l'évaluation). Facultative. Si « Diagnostic de la voix » signale une erreur 404 sur le modèle, mettez ici le nom exact du modèle de synthèse vocale donné par Google AI Studio, puis **Deploy**. | absente |
| `GEMINI_TTS_TIMEOUT` | Délai d'attente d'une demande de synthèse vocale pendant la préparation, en secondes. Facultative. | absente (60) |
| `OPENAI_API_KEY` | Clé OpenAI (compte prépayé) : **secours** de la synthèse vocale des messages (bienvenue, résultat de l'évaluation), du modèle texte (lecture des captures de paiement et de « Mes cours », estimation du niveau, rédactions) et des **séances orales en direct** (évaluation, séance du jour, « Mes cours », bilan de niveau) quand Gemini atteint sa limite, est en panne ou trop lent. Facultative : absente, OpenAI est simplement ignoré (les séances orales en direct restent alors chez Gemini seul). À coller seulement dans Laravel Cloud, jamais ailleurs. | absente |
| `TTS_PROVIDERS` | Ordre des fournisseurs de synthèse vocale, séparés par une virgule : le premier d'abord, le suivant en secours. Facultative. `openai` seul : Gemini n'est plus utilisé pour les messages. | absente (`gemini,openai`) |
| `OPENAI_TTS_MODEL` | Modèle de synthèse vocale d'OpenAI. Facultative. | absente (`gpt-4o-mini-tts`) |
| `TEXT_PROVIDERS` | Ordre des fournisseurs du modèle texte, séparés par une virgule : le premier d'abord, le suivant en secours pour la même demande. Facultative. `openai` seul : Gemini n'est plus utilisé pour lire les captures et les leçons. | absente (`gemini,openai`) |
| `OPENAI_TEXT_MODEL` | Modèle texte d'OpenAI (lit les images et les PDF, réponse au format imposé). Facultative. | absente (`gpt-6-luna`, 0,10 $ par million de jetons en entrée, 0,50 $ en sortie) |
| `OPENAI_TEXT_REASONING` | Réflexion d'OpenAI pour les lectures : `none`, `low`, `medium` ou `high`. Facultative. | absente (`low`) |
| `OPENAI_TEXT_INPUT_PRICE`, `OPENAI_TEXT_OUTPUT_PRICE`, `GEMINI_TEXT_INPUT_PRICE`, `GEMINI_TEXT_OUTPUT_PRICE` | Tarifs en dollars par million de jetons, seulement pour l'estimation de la page « Consommation ». Facultatives (Gemini : à régler si vous passez au palier payant). | absentes (OpenAI 0,10 et 0,50 ; Gemini 0) |
| `LIVE_PROVIDERS` | Ordre des fournisseurs des séances orales en direct, séparés par une virgule. Le fournisseur est choisi au début de chaque séance et ne change jamais pendant la séance. Facultative. `openai` seul : toutes les séances se font chez OpenAI (pour un essai). | absente (`gemini,openai`) |
| `OPENAI_REALTIME_MODEL` | Modèle de conversation vocale en direct d'OpenAI. Facultative (plus naturel mais environ trois fois plus cher : `gpt-realtime-2.1`). | absente (`gpt-realtime-2.1-mini`, audio : 10 $ par million de jetons en entrée, 20 $ en sortie) |
| `OPENAI_REALTIME_VOICE` | Voix de Teacher Joe chez OpenAI. Facultative (autre voix recommandée : `marin`). | absente (`cedar`, voix masculine) |
| `OPENAI_REALTIME_INPUT_TRANSCRIPTION` | Transcription de ce que dit l'apprenant pendant une séance chez OpenAI (sous-titres « Vous » et texte des conversations relu dans l'administration). Facturée à part par OpenAI : 0,003 $ par minute de voix de l'apprenant (`gpt-4o-mini-transcribe`, le moins cher). `false` la coupe : la séance marche, mais sans le texte de l'apprenant chez OpenAI. Chez Gemini, la transcription n'a pas de prix à part. | absente (activée) |
| `OPENAI_REALTIME_COST_PER_MINUTE`, `GEMINI_LIVE_COST_PER_MINUTE` | Coût estimé par minute de séance, seulement pour la page « Consommation ». Facultatives (Gemini : à régler si vous passez au palier payant). | absentes (OpenAI 0,04 $ ; Gemini 0) |
| `OPENAI_REALTIME_INPUT_PRICE`, `OPENAI_REALTIME_OUTPUT_PRICE` | Tarif d'OpenAI en dollars par million de jetons, pour le coût par apprenant et par séance de la page « Consommation » (chaque séance est comptée au tarif de son fournisseur : Gemini avec `GEMINI_PRICE_INPUT` / `GEMINI_PRICE_OUTPUT`, OpenAI avec ces deux variables). Facultatives. | absentes (10 $ en entrée, 20 $ en sortie : tarif audio de `gpt-realtime-2.1-mini`) |
| `OPENAI_TTS_VOICE` | Voix d'OpenAI. Facultative (autres voix : marin, ash, onyx, echo…). | absente (`cedar`, voix masculine posée et chaleureuse) |
| `GEMINI_END_OF_SPEECH_SENSITIVITY` | Fin de parole de l'apprenant chez Gemini : `END_SENSITIVITY_HIGH` (par défaut, la valeur de Google) ou `END_SENSITIVITY_LOW` (Gemini attend davantage avant de répondre ; avec du bruit de fond, il peut ne jamais répondre). Ne la créez pas sans raison. | absente |
| `GOOGLE_SITE_VERIFICATION` | Code de Google Search Console (méthode « Balise HTML » : seulement ce qui est entre les guillemets de `content="…"`). Remplie, la balise est ajoutée en haut de chaque page. Ce n'est pas un secret. | absente |
| `REDIRECT_FROM_HOSTS` | Changement de domaine : anciennes adresses, sans `https://`, séparées par une virgule (par exemple `talkami.laravel.cloud`). Chaque page ouverte sur l'une d'elles est redirigée (301) vers la même page sur `APP_URL`. Voir l'étape 11 bis. | absente |
| `WHATSAPP_GROUP_URL` | Lien d'invitation du **groupe** WhatsApp (`https://chat.whatsapp.com/…`). Rempli, le bouton WhatsApp de la page « introuvable » (404) ouvre le groupe ; absent, il ouvre une conversation avec le numéro WhatsApp de l'équipe. | absente |
| `GEMINI_TOKEN_MINUTES` | Durée d'un jeton éphémère, en minutes. | `5` |
| `AVATAR_3D_ENABLED` | `false` désactive le professeur 3D partout. | `true` |
| `APP_FORCE_HTTPS` | Redirection vers HTTPS. Active en production si la variable est **absente** : ne la laissez jamais **vide**. | absente |
| `TRUSTED_PROXIES` | Proxys de confiance. **Laissez-la absente** : en production, tous les proxys sont crus (le serveur de Laravel Cloud n'est joignable que par son réseau). | absente (équivaut à `*`) |
| `PRIVATE_FILES_DISK` | Disque des fichiers privés. **Laissez-la absente** : le bucket par défaut est utilisé. | absente |

### 7.3 Ajoutées par Laravel Cloud tout seul (ne les créez pas)

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (quand la base est attachée) ;
- `FILESYSTEM_DISK` et `AWS_BUCKET`, `AWS_ENDPOINT_URL`, `AWS_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (quand le bucket est attaché comme disque par défaut) ;
- `LARAVEL_CLOUD=1` et quelques autres variables internes.

Vous pouvez les voir dans **Settings, General**. Si vous créez vous-même une variable portant le même nom, la vôtre l'emporte : évitez-le.

### 7.4 Temporaires (étape 10 seulement)

| Nom | Rôle | Exemple (fictif) |
|---|---|---|
| `ADMIN_PHONE` | Numéro du compte administrateur à créer. À supprimer tout de suite après. | `+22890000000` |
| `ADMIN_PASSWORD` | Mot de passe de l'administrateur (10 caractères minimum, **sans espace ni « # »**). À supprimer tout de suite après. | `exemple-fictif-123` |

---

## 8. Générer la clé `APP_KEY` et lancer le premier déploiement

Sur un téléphone, on ne peut pas lancer d'ordinateur local : on demande la clé à Laravel Cloud lui-même.

1. **Sans `APP_KEY`**, touchez **Deploy** (bouton de l'environnement). La construction et la migration de la base ne demandent pas de clé.
2. Allez dans l'onglet **Deployments** : vous devez voir le déploiement passer en **vert** (voir « Savoir si un déploiement a réussi » plus bas).
3. Ouvrez l'onglet **Commands** de l'environnement, tapez la commande ci-dessous et touchez **Run** :

   ```sh
   php artisan key:generate --show
   ```

   Vous devez voir une ligne qui commence par `base64:`. **Copiez-la en entier** (appui long, puis Copier).
4. Retournez dans **Settings, General, Environment variables**, ajoutez `APP_KEY` avec cette valeur, **Save**, puis **Deploy**.
5. Vous devez voir : un nouveau déploiement vert. Ouvrez l'adresse du site (bouton **Visit** ou l'adresse en `laravel.cloud`) : la page d'accueil de Talkami s'affiche, en https.

> Gardez la clé `APP_KEY` précieusement (gestionnaire de mots de passe). Si elle change, toutes les connexions en cours sont perdues.

---

## 9. Activer le planificateur de tâches (Scheduler)

Le projet a six tâches automatiques (la sixième, `payments:alert-expiring`, tourne toutes les dix minutes : voir l'étape 10 ter) : fermeture des séances abandonnées (chaque minute), fin des abonnements (chaque heure), préparation des messages vocaux qui manquent encore (chaque heure, aucune demande à Google quand tout est prêt), effacement du texte des conversations de plus de 90 jours (chaque nuit à 3 heures 10) et un témoin qui prouve que le planificateur tourne (chaque minute). Il faut activer l'option dans Laravel Cloud :

1. Sur le canvas, touchez la carte **App** (le cluster de l'application).
2. Activez l'interrupteur **Scheduler**.
3. Touchez **Save** (ou « Save and redeploy ») puis **Deploy**.
4. Vous devez voir : un déploiement vert. Laravel Cloud lance alors `php artisan schedule:run` chaque minute.

Pourquoi c'est important : sans le planificateur, une séance dont le navigateur a été fermé n'est jamais fermée ni décomptée.

---

## 10. Créer le compte administrateur

La commande `php artisan app:create-admin` **demande** le numéro et le mot de passe (saisie sans affichage, deux fois). Mais l'onglet **Commands** de Laravel Cloud ne permet de répondre à aucune question. On donne donc le numéro et le mot de passe **temporairement**, par deux variables, puis on les supprime.

1. **Settings, General, Environment variables** : ajoutez `ADMIN_PHONE` et `ADMIN_PASSWORD` (voir le tableau 7.4). Choisissez un vrai mot de passe long, que vous n'utilisez nulle part ailleurs, **sans espace ni « # »** (dans les variables d'environnement, « # » commence un commentaire et un espace coupe la valeur : le mot de passe enregistré ne serait pas celui que vous croyez). **Save**, puis **Deploy** (vert).
2. Onglet **Commands** : tapez, puis **Run** (l'option `--no-interaction` est **obligatoire**) :

   ```sh
   php artisan app:create-admin --no-interaction
   ```

   Sans `--no-interaction`, l'onglet **Commands** se présente comme un terminal : la commande pose sa question (« Numéro de téléphone »), personne ne peut y répondre et elle s'arrête sur « Aborted ». Rien n'est créé : relancez simplement avec l'option.

   Vous devez voir : « Administrateur créé : +228••••••00 » (le numéro est volontairement masqué, le mot de passe n'est jamais affiché) et un rappel de supprimer les variables.
3. **Supprimez `ADMIN_PHONE` et `ADMIN_PASSWORD`** des variables, **Save**, puis **Deploy**. Cette étape est obligatoire : tant que les variables existent, le mot de passe reste lisible dans la configuration du serveur.
4. Ouvrez le site, **Connexion**, avec ce numéro et ce mot de passe : l'onglet **Administration** doit apparaître.

Pour créer un autre administrateur plus tard, ou promouvoir un compte existant, refaites les mêmes étapes (si le numéro existe déjà, le compte est promu ; mot de passe laissé vide = mot de passe conservé).

### 10 bis. Les Réglages (facultatif)

**Rien à saisir pour ouvrir le site** : les numéros Mobile Money, les noms des titulaires et les coordonnées de l'équipe (téléphones, e-mail, adresse, WhatsApp) ont des valeurs par défaut dans le code (`config/payment.php` et `config/platform.php`). Dès le déploiement, les dix opérateurs (Togo : Mixx by Yas, Flooz ; Burkina Faso : Orange Money, Wave ; Bénin : MTN, Moov, Celtiis ; Côte d'Ivoire : Orange Money, MTN Mobile Money, Wave) sont proposés aux apprenants, et les coordonnées s'affichent dans le pied de page et sur la page Contact.

Pour changer un numéro sans toucher au code :

1. Sur le site, connecté en administrateur : **Administration**, puis l'onglet **Réglages** (adresse `/admin/reglages`).
2. Sous chaque champ vide, le site indique la valeur par défaut utilisée. Tapez seulement ce qui doit changer (numéro avec l'indicatif, par exemple `+228 …`, ou nom du titulaire tel qu'il apparaît sur le message de confirmation).
3. **Enregistrer les réglages**. Rien à redéployer : c'est enregistré dans la base, et la valeur saisie remplace aussitôt la valeur par défaut.
4. Pour revenir à la valeur par défaut, videz le champ et enregistrez.

Le numéro utilisé sert aussi à vérifier les captures : la règle « Bon destinataire » compare le numéro lu sur la capture à celui-ci. Un numéro mal tapé = aucune capture acceptée automatiquement (les paiements restent alors en attente de votre vérification).

---

## 10 ter. Les alertes de paiement (notification sur le téléphone et e-mail)

Dès qu'une capture de paiement arrive, qu'un accès provisoire va se terminer dans deux heures sans votre validation, ou qu'un apprenant d'un pays sans paiement en ligne touche « Écrire sur WhatsApp », le site vous prévient par une **notification sur votre téléphone** et par **e-mail**. Si l'envoi échoue, l'apprenant ne voit rien : l'échec est noté dans Administration, **Alertes**.

### Variables à créer dans Laravel Cloud (Settings, General, Environment variables)

| Nom | Rôle |
|---|---|
| `ADMIN_ALERT_EMAIL` | Une ou plusieurs adresses qui reçoivent les alertes, séparées par des virgules. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | E-mail par Brevo : `smtp`, `smtp-relay.brevo.com`, `587`, puis vos identifiants Brevo, l'adresse d'expéditeur vérifiée et `Talkami`. Aucune autre variable n'est nécessaire : le port 587 passe en connexion chiffrée (STARTTLS) tout seul. |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Clés des notifications. `VAPID_SUBJECT` = `mailto:` suivi de votre adresse. |

Créer les deux clés VAPID (une seule fois) : onglet **Commands** de l'environnement, lancez `php artisan alerts:generate-vapid`, copiez les deux premières lignes dans les variables, puis **redéployez**. La clé privée est un secret : ne la collez nulle part ailleurs.

### Activer les notifications sur le Pixel 6

1. Ouvrez le site dans Chrome, connectez-vous en administrateur, puis menu de Chrome, **Installer l'application** (ou le bouton « Installer l'application » en bas du site). Ouvrez ensuite **l'application Talkami installée** (l'icône sur l'écran d'accueil), pas Chrome.
2. Administration, onglet **Alertes**, touchez **Recevoir les alertes sur ce téléphone**, puis **Autoriser**.
3. Touchez **Envoyer une alerte d'essai** : la notification « Alerte d'essai Talkami » doit apparaître. Touchez-la : elle ouvre l'administration. Touchez aussi **Envoyer un e-mail d'essai**.

### Laisser passer Talkami en mode « Ne pas déranger » (Pixel 6)

Réglages, **Notifications**, **Ne pas déranger**, **Personnes et applications** (ou **Applications**), **Applications pouvant interrompre**, **Ajouter des applications** (ou **Applications** puis **Ajouter**), choisissez **Talkami**. Ne cochez ni Chrome ni WhatsApp. Ensuite : Réglages, **Applis**, **Talkami**, **Notifications**, vérifiez que « Toutes les notifications Talkami » est activé et que la catégorie est en **Alerte** (son et vibration).

Attention, point à vérifier sur le téléphone : Android attribue les notifications d'une application installée depuis Chrome à l'application **Talkami** (c'est une « WebAPK »), pas à Chrome, si l'abonnement a été fait **depuis l'application installée**. Si la notification d'essai apparaît sous le nom « Chrome » (ou « Chrome : talkami.net ») au lieu de « Talkami », l'abonnement a été fait depuis Chrome : dans l'application Talkami, touchez **Arrêter les alertes sur ce téléphone** puis **Recevoir les alertes sur ce téléphone** de nouveau. Si les notifications restent sous Chrome malgré cela, la meilleure solution est de laisser passer Chrome pour les seules notifications de talkami.net (Réglages, Applis, Chrome, Notifications, catégorie « talkami.net »), et de recevoir en plus l'e-mail : l'e-mail est le filet de sécurité.

### E-mail depuis une adresse Gmail

Un premier e-mail envoyé par Brevo avec une adresse Gmail comme expéditeur peut être classé dans les **courriers indésirables** (Gmail demande que le domaine de l'expéditeur autorise l'envoi). Ouvrez-le dans Spam et touchez **Pas du spam** : les suivants arrivent dans la boîte principale. Plus durable : acheter un nom de domaine, le vérifier dans Brevo (enregistrements SPF et DKIM) et utiliser une adresse à ce domaine pour `MAIL_FROM_ADDRESS`.

---

## 11. Brancher le nom de domaine

1. **Settings, Network** (réseau) de l'environnement, puis **Add domain**.
2. Entrez votre domaine, par exemple `exemple-talkami.com`. Laissez **« Redirect from www »** activé (recommandé). Si vous ne savez pas, choisissez la voie « flexible » (courte coupure possible, sans importance au lancement).
3. Laravel Cloud affiche des **enregistrements DNS** (type, nom, valeur). Allez chez le fournisseur qui gère le DNS de votre domaine (pas toujours celui où vous l'avez acheté), et recopiez-les **exactement** : champ « nom » = seulement la partie avant le domaine (`www`, `@` pour la racine). Ajoutez ceux de `www` aussi.
4. Retournez sur Laravel Cloud, touchez **Refresh** jusqu'à ce que le domaine affiche **Connected** (le plus souvent moins de 15 minutes ; jusqu'à 12 heures).
5. Le certificat HTTPS est créé et renouvelé automatiquement.
6. Changez `APP_URL` avec votre domaine (`https://exemple-talkami.com`), **Save**, **Deploy**. C'est `APP_URL` qui donne les adresses du référencement (adresse canonique, image et lien de partage sur WhatsApp et Facebook, `sitemap.xml`, `robots.txt`) : après ce déploiement, ouvrez `https://votre-domaine/robots.txt` et vérifiez que la ligne `Sitemap:` montre bien votre domaine.
7. Facultatif, après avoir vérifié que le site s'ouvre bien en https : dans **Settings, Network**, activez **HSTS** (le navigateur n'ouvrira plus jamais le site en http).

L'offre Starter inclut 10 domaines personnalisés par organisation.

---

## 11 bis. Changer de domaine sans perdre le référencement (talkami.laravel.cloud vers talkami.net)

1. Branchez le nouveau domaine (étape 11, points 1 à 5) et attendez **Connected**.
2. Dans **Settings, Environment variables** : `APP_URL=https://talkami.net` et `REDIRECT_FROM_HOSTS=talkami.laravel.cloud`, **Save**, **Deploy**.
3. Vérifiez sur le téléphone : `https://talkami.laravel.cloud/formules` doit ouvrir `https://talkami.net/formules` (redirection 301 : Google transfère le référencement de chaque page) ; `https://talkami.net/robots.txt` montre `Sitemap: https://talkami.net/sitemap.xml`.
4. Google Search Console : ajoutez la propriété `https://talkami.net/` (étape 11 ter), puis dans l'**ancienne** propriété : **Paramètres**, **Changement d'adresse**, choisissez la nouvelle, **Valider et mettre à jour**. Envoyez ensuite `https://talkami.net/sitemap.xml` dans la nouvelle propriété.
5. Gardez `REDIRECT_FROM_HOSTS` au moins un an (Google conseille de garder les redirections longtemps).

## 11 ter. Google Search Console, depuis un téléphone

1. Ouvrez `search.google.com/search-console` dans Chrome, connectez-vous avec le compte Google de l'équipe.
2. **Ajouter une propriété**, choisissez **Préfixe de l'URL**, entrez l'adresse exacte du site (`https://talkami.laravel.cloud/` aujourd'hui, `https://talkami.net/` après le changement), **Continuer**.
3. Méthode **Balise HTML** : copiez seulement le code entre les guillemets de `content="…"`.
4. Laravel Cloud : variable `GOOGLE_SITE_VERIFICATION` = ce code, **Save**, **Deploy**.
5. Revenez sur Search Console, touchez **Valider**.
6. Menu **Sitemaps** : entrez `sitemap.xml`, **Envoyer**. L'état doit devenir « Opération effectuée » (10 pages).
7. Menu **Inspection de l'URL** : collez l'adresse de l'accueil, puis **Demander une indexation** ; recommencez pour `/formules`, `/a-propos` et les quatre pages par pays.
8. Quelques jours plus tard, menu **Pages** : les pages publiques doivent être « Indexée » ; les pages privées n'y apparaissent pas (elles portent noindex).

## 12. Vérifier que tout fonctionne

### 12.1 La commande de contrôle

Onglet **Commands** :

```sh
php artisan app:check-deployment
```

Elle ne montre aucun secret. Elle vérifie, avec « OK » ou « À CORRIGER » et le conseil à suivre :

- la sécurité : production, débogage coupé, HTTPS, cookies sécurisés, adresse en `https://`, fuseau Africa/Lome, clé présente, sessions et cache en base ;
- la base de données : connexion MySQL et migrations appliquées ;
- le **disque privé** : c'est bien le bucket (`s3`), et un fichier test y est écrit, relu puis supprimé ; elle affiche aussi **le nombre de captures de paiement et d'attestations enregistrées dans le bucket** ;
- le **planificateur** : « Dernier passage du planificateur il y a N secondes » (doit être inférieur à 3 minutes) ;
- les **moyens de paiement** : nombre d'opérateurs proposés (valeurs par défaut ou Réglages), numéro WhatsApp présent, nombre de valeurs saisies dans les Réglages (sans jamais afficher un numéro) ;
- la présence de la clé Gemini (« oui » ou « non », jamais la valeur).

Si le planificateur est signalé : attendez deux minutes après le déploiement de l'étape 9 et relancez.

### 12.2 Le planificateur, à l'œil

Commande `php artisan schedule:list` : elle affiche les cinq tâches et leur prochaine exécution.

### 12.3 Les captures de paiement dans le bucket

1. Avec un compte de test (pas l'administrateur), faites un paiement de test (parcours **Abonnement**), avec une vraie capture d'écran de confirmation.
2. Connectez-vous en administrateur : **Paiements en attente**, ouvrez la capture. Si l'image s'affiche, elle a été lue depuis le bucket (le site n'a pas d'autre endroit où la prendre). Sous la capture : ce que l'IA a lu et chaque règle (✓ ou ✗ avec la raison). Si toutes les règles sont remplies, le compte de test a un accès provisoire de 24 heures ; validez ou refusez pour terminer le test.
3. Relancez `php artisan app:check-deployment` : « Captures de paiement enregistrées : 1 ».
4. Dans Laravel Cloud : **Organization, Resources, Object storage**, touchez le bucket, section **Metrics** : les opérations d'écriture (Class A) et de lecture (Class B) apparaissent.
5. Vérifiez que le bucket est **privé** : **Organization, Resources, Object storage**, bucket, **Edit settings** : la visibilité doit être **Private** et aucune adresse publique ne doit être affichée.

Pour une attestation : téléchargez-en une depuis le compte d'un apprenant qui a réussi un bilan de niveau ; le PDF est alors enregistré dans le dossier `attestations/` du même bucket, et `app:check-deployment` l'ajoute à son décompte « Attestations enregistrées ».

### 12.4 Les limites d'envoi de « Mes cours » (PDF de 20 mégaoctets, lecture d'une minute)

La documentation de Laravel Cloud ne dit ni la taille maximale d'un envoi ni la durée maximale d'une page : il faut les essayer sur le site en ligne.

1. Connectez-vous en administrateur, onglet **Diagnostic des fournisseurs**, bloc **Limites d'envoi**. Les trois lignes (taille d'un fichier, taille d'un envoi, durée d'une page) sont les réglages du site lui-même : chacune doit dire « suffisant ».
2. Touchez **Essayer un envoi de 20 mégaoctets** (en wifi de préférence : 20 mégaoctets sont vraiment envoyés). « Réussi » : un PDF de cette taille passe. « Échec » : le message dit ce qui bloque.
3. Touchez **Essayer une attente de 60 secondes** et gardez la page ouverte. « Réussi » : une longue lecture n'est pas coupée. « Échec après … secondes » : le serveur coupe avant la fin.
4. Si un essai échoue, rien ne casse pour les élèves : ils voient un message clair (« Votre fichier est trop lourd pour être envoyé : envoyez seulement les pages utiles », ou « La lecture prend plus de temps que prévu… ») et peuvent envoyer moins de pages. Écrivez alors au support de Laravel Cloud pour demander une limite plus haute.
5. `php artisan app:check-deployment` affiche aussi ces valeurs (section « Envoi de fichiers ») ; ce sont celles de la ligne de commande, qui peuvent différer de celles du site : l'essai du point 2 fait foi.

---

## 13. Commandes à connaître

| Quand | Où | Commande |
|---|---|---|
| Construction (à chaque déploiement) | Settings, Deployments, **Build commands** | `composer install --no-dev --optimize-autoloader` puis `php artisan optimize` |
| Déploiement (à chaque déploiement) | Settings, Deployments, **Deploy commands** | `php artisan migrate --force` puis `php artisan app:prepare-voice` |
| Préparer les messages vocaux à la main | Onglet **Commands** (ou l'administration : « Diagnostic de la voix ») | `php artisan app:prepare-voice` |
| Générer `APP_KEY` (une seule fois) | Onglet **Commands** | `php artisan key:generate --show` |
| Créer l'administrateur | Onglet **Commands** | `php artisan app:create-admin --no-interaction` |
| Contrôler la mise en ligne | Onglet **Commands** | `php artisan app:check-deployment` |
| Voir les tâches planifiées | Onglet **Commands** | `php artisan schedule:list` |
| Clore à la main les séances abandonnées | Onglet **Commands** | `php artisan live:close-stale` |
| Faire expirer les abonnements à la main | Onglet **Commands** | `php artisan subscriptions:expire` |

Les commandes de l'onglet **Commands** ne peuvent poser aucune question et s'arrêtent après 30 minutes.

---

## 14. Savoir si un déploiement a réussi ou échoué

- Chaque fois que du code arrive sur `main` (par exemple après la fusion d'une pull request), Laravel Cloud démarre un déploiement tout seul : onglet **Deployments**.
- **Vert / Succeeded** : le nouveau code est en ligne, sans coupure.
- **Rouge / Failed** : l'ancien site continue de tourner (rien n'est cassé). Touchez le déploiement : le journal montre l'étape qui a échoué (**Build** : `composer install` ou `optimize` ; **Deploy** : `migrate`). Copiez les dernières lignes et envoyez-les à Claude pour qu'il corrige.
- Le fichier `.github/workflows/tests.yml` du dépôt lance aussi les tests à chaque pull request : une pull request dont les tests échouent ne doit pas être fusionnée.
- Pour redéployer sans nouveau code (après avoir changé une variable) : bouton **Deploy**.
- Pour remettre une version précédente : **Deployments**, un ancien déploiement vert, puis son menu pour le redéployer.

---

## 15. Ce que l'offre Starter inclut, et ce qui est facturé en plus

*Source : documentation officielle de Laravel Cloud (pages « Plans and pricing », « Free trial », « Compute », « Laravel MySQL », « Laravel Object Storage », « Domains », « Spending limits »), lue le **17 octobre 2026**. Les prix sont en dollars américains et peuvent changer : relisez <https://laravel.com/cloud/docs/pricing> avant de vous engager.*

### Inclus dans l'offre Starter (5 $ par mois)

- **5 $ de crédit d'utilisation chaque mois**, déduits de tout ce qui est consommé (calcul, base, bucket…). Si l'utilisation reste sous 5 $, rien de plus n'est payé.
- **Le premier mois (31 jours) est offert** : les 5 $ d'abonnement ne sont pas facturés, et vous recevez 5 $ de crédit. Seule l'utilisation au-delà de ces 5 $ est facturée.
- Applications, environnements et constructions **illimités**.
- **Planificateur de tâches** (Scheduler) et **mise en veille automatique** (Scale-to-Zero, activée par défaut).
- **Domaines personnalisés** : 10 par organisation, **certificat HTTPS inclus** ; protection de base contre les attaques par déni de service.
- **Limites de dépense** et alertes.
- Authentification à deux facteurs.
- Limites de l'offre : un seul serveur d'application (pas d'ajout automatique de serveurs), tailles **Flex** uniquement pour l'application et la base MySQL, conservation des journaux et des mesures **1 jour**. L'authentification HTTP de base (mot de passe devant le site) n'est pas incluse (offre Growth).

### Facturé en plus (au-delà des 5 $ de crédit), en Europe

| Poste | Prix d'après la documentation | Pour Talkami |
|---|---|---|
| Application, Flex 512 MiB (1 processeur) | 7,00 $ par mois si le serveur tourne toute la période (facturé à la seconde, plafonné à 28 jours) | 7,00 $ au maximum |
| Application, Flex 1 Go (si 512 Mo devient juste) | 14,00 $ au maximum par mois | non prévu |
| Base MySQL Flex 512 MiB | 7,70 $ au maximum par mois (facturé seulement quand elle est éveillée) | 7,70 $ au maximum |
| Stockage de la base | 0,12 $ par Go et par mois (en Europe), 5 Go au minimum, toujours facturé | 0,60 $ |
| Sauvegardes de la base | même prix que le stockage (0,12 $ par Go et par mois), par défaut 7 jours | quelques centimes |
| Bucket (stockage d'objets) | 0,02 $ par Go et par mois ; 5 $ par million d'opérations d'écriture et 0,50 $ par million d'opérations de lecture ; **transfert de données gratuit** | quelques centimes |
| Transfert de données (bande passante) | 50 Go par mois offerts avec un Flex 512 MiB, puis 0,10 $ par Go | probablement 0 $ (les conversations vocales passent directement entre le navigateur et Google ou OpenAI, pas par le serveur) |
| Domaines personnalisés au-delà de 10 | 0,25 $ par domaine et par mois | 0 $ |

### Estimation pour Talkami (calcul, pas une promesse)

La documentation dit que l'environnement **se met en veille** (et ne coûte plus rien) quand personne ne visite le site, **sauf** si une tâche planifiée revient plus souvent que le délai de veille. Talkami a une tâche **chaque minute** (fermeture des séances abandonnées) : le serveur et la base **ne dormiront donc quasiment jamais**. Il faut compter le **cas toujours allumé** :

- Application Flex 512 MiB : 7,00 $
- Base MySQL Flex 512 MiB : 7,70 $
- Stockage de la base (5 Go) : 0,60 $
- Sauvegardes et bucket : environ 0,20 $
- **Utilisation : environ 15,50 $ par mois**
- moins 5 $ de crédit, plus 5 $ d'abonnement Starter : **environ 15,50 $ par mois en tout** (soit environ 9 500 FCFA)
- **Premier mois : environ 10,50 $** (abonnement offert, 5 $ de crédit déduits de l'utilisation).

Si le site grandit (beaucoup d'apprenants en même temps), le premier signe est un site lent : passez alors l'application en Flex 1 Go (+7 $). Au-delà de 1 serveur, il faut l'offre **Growth** (20 $ par mois).

### Se protéger d'une facture inattendue

**Organization, Settings, Billing, Spending limit** : saisissez un montant mensuel (par exemple 25 $). Laravel Cloud envoie des alertes à 50 %, 80 % et 90 %. Avec l'option **Stop all compute**, il arrête les serveurs en atteignant la limite (le site devient indisponible tant que la limite n'est pas relevée ; le stockage est conservé). Choisissez « alertes seulement » si vous préférez que le site ne s'arrête jamais.

Pour suivre la consommation : **Organization, Usage** (mise à jour toutes les heures).

---

## 16. Ce qui reste à faire à la main (et que personne ne peut faire à votre place)

1. Créer le compte Laravel Cloud, choisir Starter, saisir la carte bancaire.
2. Autoriser GitHub, créer l'application, la base, le bucket (étapes 2 à 6).
3. Coller les variables, y compris la clé Gemini et la clé `APP_KEY` (étapes 7 et 8).
4. Activer **Scheduler** (étape 9).
5. Créer l'administrateur avec les variables temporaires, puis les supprimer (étape 10).
6. Acheter ou choisir le nom de domaine, recopier les enregistrements DNS (étape 11).
7. Régler la limite de dépense (partie 15).
8. Faire un paiement de test et vérifier la capture dans le bucket (étape 12.3).
9. Créer les clés des alertes, les variables d'e-mail, et activer les notifications sur votre téléphone (étape 10 ter).

---

## 17. Dépannage rapide

| Symptôme | Cause probable | Que faire |
|---|---|---|
| Page blanche ou « 500 » juste après le premier déploiement | `APP_KEY` absente | Étape 8 |
| « Trop de redirections » | Le proxy n'envoie pas le protocole https | Ajoutez `APP_FORCE_HTTPS=false`, **Deploy**, et prévenez Claude |
| Déploiement rouge à l'étape **Build** | `composer install` ou `optimize` en échec | Lisez les dernières lignes du journal |
| Déploiement rouge à l'étape **Deploy** : « Command cancelled » | `--force` oublié | `php artisan migrate --force` |
| Les séances abandonnées ne sont jamais fermées | **Scheduler** désactivé | Étape 9 ; contrôlez avec `app:check-deployment` |
| « Le message vocal n'est pas disponible pour le moment » (bienvenue, résultat) | Synthèse vocale en échec (limite de Google, crédit OpenAI, modèle, stockage) ou parties communes pas encore préparées | Administration, « Diagnostic de la voix » : « Tester Gemini (Google) » et « Tester OpenAI » donnent la cause et le code HTTP ; puis « Préparer le message de bienvenue » jusqu'à « Tout est prêt ». Avec `OPENAI_API_KEY`, un message que Gemini ne peut pas finir est préparé en entier chez OpenAI (une seule voix par message). |
| « Nous vérifions votre paiement » pour chaque capture, ou « La lecture est momentanément indisponible » dans « Mes cours » | Modèle texte en échec chez Gemini (limite, panne) et chez OpenAI (clé absente, crédit épuisé) | Administration, « Diagnostic des fournisseurs » : « Tester Gemini » et « Tester OpenAI » donnent la cause et le code HTTP ; « Dernières erreurs » montre quel fournisseur a échoué. Avec `OPENAI_API_KEY`, chaque demande refusée par Gemini est refaite chez OpenAI. |
| « Le professeur est momentanément indisponible » au début d'une séance | Gemini Live en panne ou à sa limite, et OpenAI absent (pas de clé) ou en échec | Administration, « Diagnostic des fournisseurs », partie « Séances en direct » : « Tester Gemini » et « Tester OpenAI », puis « Essayer une conversation de 30 secondes » ; « Dernières erreurs des séances en direct » dit quel fournisseur a échoué et pourquoi. Avec `OPENAI_API_KEY`, une séance que Gemini ne peut pas ouvrir commence chez OpenAI. |
| Les captures disparaissent après un déploiement | Le bucket n'est pas le disque par défaut | Étape 6 (case « Set as default disk »), puis `app:check-deployment` |
| Déconnexion de tous les apprenants à chaque déploiement | Sessions sur le disque du serveur | `SESSION_DRIVER=database` |
| Le premier chargement, après une longue pause, est un peu lent | L'environnement se réveillait | Normal avec la mise en veille (moins d'une seconde avec les tailles Flex actuelles) |
| Domaine bloqué sur « Verifying » | Enregistrement DNS mal recopié | Vérifiez chaque enregistrement ; voir la page « Domain Verification » de la documentation |
