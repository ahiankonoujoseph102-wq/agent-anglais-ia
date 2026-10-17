# CLAUDE.md — Talkami (professeur : Teacher Joe)

Ce fichier est le point de départ de chaque session de développement. Le lire en entier avant de toucher au code, et le mettre à jour quand une décision change.

## Le projet

La plateforme s'appelle **Talkami** (`config('platform.name')`) ; son professeur IA s'appelle **Teacher Joe** (`config('avatar.model.name')`). Ne pas confondre les deux, et n'écrire aucun des deux noms en dur. Identité visuelle complète : `docs/charte-graphique.md`.

Plateforme web qui enseigne l'anglais aux francophones d'Afrique grâce à un professeur IA.

Parcours de l'apprenant :

1. Il crée un compte avec son **nom** et son **numéro de téléphone** (+ mot de passe).
2. Il passe une **évaluation orale gratuite** avec le professeur IA : 5 minutes maximum, **une seule par compte**.
3. Le professeur lui annonce son **niveau CECRL** (A1, A2, B1, B2, C1, C2) et l'invite à s'abonner.
4. Il **paie par Mobile Money, manuellement** : il envoie l'argent depuis son téléphone sur le numéro affiché (Togo, Burkina Faso, Bénin), puis déclare son paiement (opérateur, numéro, référence, capture d'écran). **L'administrateur valide** et l'abonnement s'active. Aucune API de paiement.
5. Une fois le paiement validé, il répond à un **questionnaire en 4 écrans** (objectif, métier, jours de travail, difficultés) et le site **construit son programme personnel** à partir du programme officiel de son niveau.
6. Chaque jour, il suit une **séance orale limitée dans le temps**. Le professeur annonce la durée au début, recadre l'apprenant qui sort du sujet, prévient 2 minutes avant la fin, résume et clôt la séance. **Le site coupe la séance automatiquement à la limite** (côté serveur, pas seulement côté navigateur).

Le professeur est un **personnage animé en 3D** dans le navigateur, dont la bouche suit la voix. La voix et l'intelligence viennent de l'**API Gemini** de Google.

## Contraintes non négociables

- **Laravel 13** (dernière stable) + **MySQL/MariaDB**.
- **Hébergement : Laravel Cloud** (décision de la phase 3, remplace Hostinger).
  - Rester simple : pas de serveur Node, pas de websockets côté serveur (Reverb), pas de worker de file d'attente. `QUEUE_CONNECTION=sync`.
  - Tâches planifiées par le **planificateur Laravel Cloud** (option « Scheduler » du cluster App) → `schedule:run` chaque minute ; chaque tâche avec `onOneServer()`.
  - **Système de fichiers éphémère** sur Laravel Cloud : tout fichier à conserver (captures de paiement) va sur un **bucket privé** (Laravel Cloud Object Storage, compatible S3), jamais sur le disque du serveur.
- **Paiement manuel uniquement** : aucune API ni aucun prestataire de paiement en ligne. Coordonnées de paiement **uniquement** dans `config/payment.php`. Au Togo : « Mixx by Yas » et « Flooz », jamais « T-Money ».
- **Interface entièrement en français, sans abréviations** (« 15 minutes », pas « 15 min »), **pensée d'abord pour le téléphone**, **légère en données** : une seule police hébergée sur le serveur (Sora, woff2, 21 Ko), pas de framework CSS ni de bibliothèque JavaScript, pas d'image inutile. Cible : moins de 150 Ko transférés pour la page d'accueil, hors image de Teacher Joe et police.
- **Aucun chiffre inventé** sur le site : ni témoignage, ni nombre d'apprenants, ni note, ni partenaire.
- **Aucune clé secrète dans le code** : tout passe par `.env` (lu via `config/*.php`, jamais `env()` ailleurs que dans `config/`).
- **Public d'abord togolais** : les situations du programme se passent au Togo (Grand Marché et port autonome de Lomé, zémidjans et taxis, universités de Lomé et de Kara, administration, accueil de visiteurs anglophones…). Le **Ghana** est le seul autre pays cité, comme voisin anglophone (frontière d'Aflao, achats et affaires à Accra). Les variétés de langue (anglais britannique, américain, du Ghana) peuvent être nommées pour la prononciation ; aucun autre pays. `tests/Unit/CurriculumTest.php` le vérifie.
- **Vocabulaire imposé** : le document délivré après un bilan de niveau réussi est une **attestation** de niveau — jamais « certificat » ni « diplôme ».
- **Nom commercial, formules, prix, durées et coordonnées du centre** : uniquement dans `config/platform.php`. Jamais en dur dans le code ou les vues → `config('platform.name')`, `App\Support\Plan::all()`, `config('platform.contact')`, etc.

Formules de départ (dans `config/platform.php`) :

| Clé | Nom | Minutes/jour | Prix | Validité |
|---|---|---|---|---|
| `essentiel` | Essentiel | 15 | 5 000 FCFA | 30 jours (par mois) |
| `intensif` | Intensif | 30 | 9 000 FCFA | 30 jours (par mois) |
| `semaine` | Semaine | 15 | 1 500 FCFA | 7 jours (par semaine) |

## Feuille de route

- **Phase 1 — Fondations (faite)** : projet Laravel, base de données, inscription/connexion par téléphone, accueil public, espace apprenant (tableau de bord), espace admin (liste des inscrits), tests.
- **Phase 2 — Conversation vocale (faite)** : évaluation orale gratuite et séance du jour avec l'API Gemini Live, jetons éphémères, limite de temps imposée par le serveur, consignes du professeur dans `config/teacher.php`, visage SVG animé, mesures de consommation dans l'admin.
- **Phase 3 — Paiement manuel et comptes (faite)** : paiement Mobile Money déclaré par l'apprenant (capture privée), validation/refus par l'administrateur, activation et renouvellement de l'abonnement, rappel 3 jours avant la fin, mot de passe oublié via WhatsApp + mot de passe provisoire, coordonnées du centre (pied de page, page Contact).
- **Phase 4 — Programme (faite)** : programme officiel original A1 → C2 (12 unités par niveau), questionnaire et programme personnel (calendrier, progression), séance branchée sur la leçon du jour, bilan structuré (notes, erreurs, devoir), erreurs à revoir, bilan de fin de niveau, attestation PDF, suivi (série, compétences), apprenants inactifs dans l'admin, Gemini texte pour les rédactions.
- **Phase 5 — Professeur 3D (faite)** : personnage 3D « Teacher Joe » (CC0, créé par script), bouche synchronisée sur la voix (HeadAudio), comportement (repos, écoute, parole, expressions déclenchées par Gemini), décor de classe, chargement en arrière-plan avec cache, retour automatique au visage SVG.
- **Phase 6 — Design et application installable (faite)** : identité visuelle provisoire, toutes les pages apprenant refaites, administration sobre, pages d'erreur, animations discrètes, manifeste web, service worker étendu, page hors connexion, bouton « Installer l'application ».
- **Phase 6 bis — Marque Talkami (faite)** : nom définitif, bleu nuit `#0E2A5C` et orange `#FF7A1A`, police Sora, les quatre logos fournis (fond rendu transparent) dans `public/brand/`, favicon et icônes de l'application tirés de l'icône, Teacher Joe en gilet bleu nuit et cravate orange, charte graphique `docs/charte-graphique.md`.

(L'ordre 2/3/4/5 est indicatif ; voir le compte rendu de chaque phase.)

## Architecture

```
app/
  Console/Commands/      app:create-admin, subscriptions:expire, live:close-stale (évaluations, séances, bilans de niveau)
  Enums/                 Level (A1…C2), Role, *Status — valeurs stockées en anglais, libellés FR via label()
  Http/Controllers/      HomeController, ContactController, CreditsController, DashboardController, Auth/* (dont PasswordController),
                         Admin/* (PaymentController, UserController, UsageController, AssessmentController)
  Http/Controllers/Live/ AssessmentController (évaluation), LessonController (séance du jour), LevelTestController (bilan de niveau)
  Http/Controllers/Program/ ProgramController (questionnaire, programme, unités), LessonHistoryController, AttestationController
  Http/Controllers/Admin/InactiveLearnerController   apprenants inactifs + message WhatsApp rédigé
  Http/Controllers/Payment/ PaymentController (parcours de paiement de l'apprenant)
  Http/Middleware/       EnsureUserIsAdmin (alias « admin »), EnsurePasswordIsChanged (groupe web)
  Http/Requests/Auth/    RegisterRequest, LoginRequest (normalisation du téléphone + limitation des essais)
  Http/Requests/Payment/ StorePaymentRequest ; Http/Requests/Admin/ RejectPaymentRequest
  Http/Requests/Live/    ConnectRequest, SignalRequest, AssessmentResultRequest, LessonReportRequest, LevelTestResultRequest
  Http/Requests/Program/ QuestionnaireRequest (règles selon l'écran 1 à 4)
  Models/                User, Assessment, Program, ProgramLesson, Subscription, Payment, Lesson, LearnerError, LevelTest, Attestation
  Models/Concerns/       IsLiveConversation (règles de temps communes à Assessment, Lesson et LevelTest)
  Enums/ (phase 4)       Goal, Difficulty, Skill, ProgramStatus, ProgramLessonStatus, LevelTestStatus, ErrorStatus
  Support/               PhoneNumber (normalisation E.164), Plan (formules depuis la config), Money, Duration,
                         PaymentCountry / PaymentOperator (config/payment.php), WhatsApp (liens wa.me), TemporaryPassword,
                         Avatar (réglages du professeur 3D pour le navigateur, URL versionnées par empreinte)
  Support/Gemini/        LiveClient (jeton éphémère), LiveConnection, TeacherSetup (consignes + config Live), UsageCost,
                         TextClient (modèle texte léger, API Interactions, renvoie null en cas d'échec)
  Support/Curriculum/    Curriculum, Unit, Situation (lecture du programme officiel), ProgramBuilder, LessonContext,
                         LessonReport, LevelProgression ; Support/AttestationPdf (PDF écrit à la main, sans bibliothèque)
resources/curriculum/    a1.php … c2.php : LE PROGRAMME OFFICIEL (données, pas de vues), modifiable sans toucher au code
config/program.php       mots-clés métier → thèmes, durée du bilan de niveau, erreurs par séance, seuil d'inactivité
config/platform.php      TOUS les réglages commerciaux (+ durée de l'évaluation, préavis de fin, signaux, coordonnées du centre)
config/payment.php       pays, opérateurs, numéros et titulaires Mobile Money ; WhatsApp ; captures ; délai du rappel
config/teacher.php       consignes du professeur (modifiables sans toucher au code ; marqueurs :platform et :teacher)
config/avatar.php        professeur 3D : fichier du modèle, cadrage, expressions proposées à Gemini, crédits
config/services.php      gemini : clé, modèle Live, version d'API, voix, durée des jetons, tarifs pour l'estimation
public/js/               live-session.js (conversation vocale), pcm-capture-worklet.js (micro → PCM 16 kHz), copy.js (bouton « Copier »),
                         teacher-view.js (choix 3D / SVG, progression, repli), teacher-3d.js (scène, animation, lecture labiale)
public/teacher3d/        models/teacher-joe.glb (personnage), lib/ (three.js regroupé, HeadAudio, LICENSES.txt)
public/sw.js             SERVICE WORKER UNIQUE du site (portée « / ») : fichiers statiques + page hors connexion (voir plus bas)
public/js/app.js         commun à toutes les pages : enregistrement du service worker, apparition des blocs, installation
public/fonts/            sora-2.000-latin.woff2 (seule police), OFL-Sora.txt (licence)
public/brand/            les quatre logos (horizontal, vertical, icone, blanc) en PNG transparent, WebP et WebP 640 pixels
docs/brand/reference/    originaux des logos (PNG fournis, fond non transparent)
tools/brand/             prepare-logos.py (EN LOCAL, Python + Pillow) : détourage, marges, WebP, favicon et icônes
public/icons/            icônes de l'application (192, 512, adaptative 512, iPhone 180, favicon 96) ; public/favicon.ico (16, 32, 48)
public/images/           teacher-joe.webp (Teacher Joe, page d'accueil)
tools/teacher3d/         outils LOCAUX (Node) : génération du modèle, regroupement de three.js (voir son README)
lang/fr/, lang/fr.json   traductions (validation, erreurs HTTP, pagination)
public/css/tokens.css    IDENTITÉ VISUELLE : seul fichier des couleurs, police, espacements, rayons, ombres, durées
public/css/app.css       styles des pages (n'utilise que les variables de tokens.css), écrit à la main, sans compilation
resources/views/
  components/layout.blade.php   gabarit commun (<x-layout>) : en-tête, barre du bas (téléphone), pied de page, manifeste ;
                                props : title, description, narrow, install (bandeau d'installation), focus (écran de séance)
  components/simple-layout.blade.php  gabarit sans session ni compte (page hors connexion, erreurs 500 et 503)
  components/input.blade.php    champ de formulaire avec erreurs (<x-input>)
  components/icon.blade.php     icônes SVG intégrées (<x-icon name="mic" />), dessinées pour le site
  components/logo.blade.php     logo (<x-logo variant="header|footer" />) : image de config('platform.brand.logo'), nom de la plateforme en texte de remplacement
  components/stepper.blade.php  fil d'étapes animé (paiement, questionnaire)
  errors/                       404, 403, 419 (page expirée), 500, 503
  offline.blade.php             « Vous êtes hors connexion »
```

### Base de données

| Table | Rôle | Points clés |
|---|---|---|
| `users` | comptes | `phone` unique au format `+22890123456`, `level` (CECRL, nullable), `role` (`learner`/`admin`), `must_change_password` (mot de passe provisoire). Pas d'e-mail. |
| `assessments` | évaluation gratuite | `user_id` **unique** → une seule évaluation par compte, garantie par la base. `allowed_seconds`, `used_seconds`, `level`, `feedback` + colonnes de suivi (ci-dessous). |
| `programs` | programme personnel (un par niveau) | `level`, `status` (`active`/`level_test_due`/`completed`/`archived`), réponses au questionnaire (`goal`, `occupation`, `interests`, `study_days` 1–7, `study_time`, `difficulties`), `content.intro` (présentation rédigée), `is_active` (un seul actif). |
| `program_lessons` | leçons du programme | `position` (ordre), `unit_number` (1–12), `lesson_number` (1–3), `situation_index`, `highlighted` (liée à l'objectif/métier), `scheduled_on`, `status` (`pending`/`completed`), `attempts`. |
| `learner_errors` | erreurs à revoir | `wrong`, `correct`, `explanation`, `status` (`open`/`resolved`), `correct_streak`, `times_seen`. Résolue après **2 corrections de suite**. |
| `level_tests` | bilans de fin de niveau | conversation vocale (mêmes colonnes de suivi que les séances), `passed`, `feedback`, `units_to_review`. |
| `attestations` | attestations de niveau | `level` (unique par apprenant), `code` de vérification unique, `issued_at`. |
| `subscriptions` | abonnements | `plan` (clé de config) + **copie** de `plan_name`, `daily_minutes`, `price`, `currency` au moment de l'achat ; `starts_at`, `ends_at`, `status`. Créé à la validation d'un paiement. |
| `payments` | paiements Mobile Money déclarés | copie de la formule (`plan`, `plan_name`, `daily_minutes`, `duration_days`, `amount`) ; `country`, `operator`, `operator_name`, `recipient_number` ; `payer_phone`, `reference`, `proof_path` (capture, disque privé) ; `status` (`pending`/`validated`/`rejected`), `rejection_reason`, `reviewed_by`, `reviewed_at`, `subscription_id`. |
| `lessons` | séances quotidiennes | `date`, `allowed_seconds`, `used_seconds` (durée consommée), `summary`, `homework`, `status` + colonnes de suivi ; `program_lesson_id`, `outcome` (`completed`/`repeat`), `score_fluency`, `score_pronunciation`, `score_grammar`, `score_vocabulary`, `score_comprehension` (1–5), `reported_at`. Nommée `lessons` car `sessions` est réservée par Laravel. |

Colonnes de suivi (assessments et lessons) : `last_seen_at` (dernier signal du navigateur), `token_expires_at` (fin du dernier jeton), `connections` (jetons délivrés), `input_tokens`, `output_tokens` (rapportés par Gemini).

Temps restant du jour = `subscription.daily_minutes × 60 − Σ lessons.used_seconds du jour` (`User::remainingSecondsToday()`), jamais négatif. Le « jour » suit `APP_TIMEZONE` (par défaut `Africa/Lome`).

### Paiement manuel (Mobile Money)

- Parcours apprenant en 4 étapes : `/abonnement` (formule) → `/abonnement/{plan}?pays=TG` (opérateurs, numéro, titulaire, montant exact, bouton « Copier le numéro » qui copie le numéro local) → formulaire (opérateur, numéro payeur, référence, capture) → `/paiements/{id}` (« Paiement en attente de validation » + bouton « Prévenir sur WhatsApp » avec message pré-rédigé).
- Règles garanties **par la base** (index uniques) :
  - `reference_key` = référence normalisée (majuscules, sans espaces ni ponctuation) : une même référence ne peut pas être envoyée deux fois. Elle est remise à `null` au refus, pour permettre un renvoi corrigé.
  - `pending_user_id` = `user_id` tant que le paiement est en attente, `null` ensuite (géré par `Payment::booted`) : un seul paiement en attente par apprenant.
- Captures : images JPG/PNG/WebP, 5 Mo max, sur le disque `config('payment.proofs.disk')` (privé), servies uniquement par la route admin `admin.payments.proof` (jamais d'URL publique).
- Validation (`Payment::approve`) : crée l'abonnement actif ; s'il en reste un en cours, le nouveau commence à sa fin (`starts_at` = fin la plus lointaine des abonnements actifs). Refus (`Payment::reject`) : motif obligatoire, affiché à l'apprenant.
- Renouvellement : rappel dans l'espace apprenant `payment.renewal_reminder_days` (3) jours avant la fin, sauf si le suivant est déjà payé. À l'expiration, `/seance` redirige vers `/abonnement` ; l'évaluation et le niveau restent visibles.

### Programme (phase 4)

- **Programme officiel** : `resources/curriculum/{a1,a2,b1,b2,c1,c2}.php`, 12 unités par niveau. Une unité = `title`, `objectives` (suites de « À la fin, vous saurez… »), `vocabulary` (15 à 25 entrées anglais ⇒ français), `grammar`, `functions` (oral), `pronunciation`, `spoken` (anglais parlé naturel), `pitfalls` (difficultés des francophones), 3 `situations` (`title`, `description`, `tags` ∈ `Curriculum::TAGS`). Contenu original ; `tests/Unit/CurriculumTest.php` vérifie la structure.
- **Construction** (`ProgramBuilder`) : après le premier paiement validé, questionnaire en 4 écrans (`/programme/questionnaire/{1..4}`, réponses gardées en session). Niveau = `users.level` (A1 si inconnu). 3 leçons par unité (une par situation) ; dans chaque unité, les situations liées à l'objectif (+2) puis au métier/centres d'intérêt (+1, `config/program.php`) passent en premier et sont marquées « pour vous ». Une leçon par jour de travail, à partir d'aujourd'hui ; le calendrier est recalculé après chaque séance.
- **Séance** : sans programme, `/seance` renvoie au questionnaire. Les consignes (`config/teacher.php` → `lesson`, marqueur `:context` rempli par `LessonContext`) contiennent prénom, niveau, objectif, métier, unité et leçon du jour (vocabulaire, grammaire…), résumé de la séance précédente, erreurs ouvertes avec leur identifiant. Structure imposée : accueil, révision des erreurs, nouveau contenu, pratique en situation, correction, résumé et devoir.
- **Bilan de séance** (fonction `end_lesson` → `POST /seance/bilan`, une fois par séance, `LessonReport`) : `lesson_status` (`completed` fait avancer le programme, `repeat` garde la leçon), `summary`, `homework`, `errors[]` (nouvelles erreurs, dédoublonnées), `reviewed_errors[]` (`id`, `corrected`), `scores` (1–5 × 5 compétences).
- **Fin de niveau** : 12 unités terminées → programme `level_test_due` → `/bilan-niveau` (conversation de `program.level_test_minutes`, fonction `record_level_test_result`). Réussi (`LevelProgression`) : attestation, niveau suivant, nouveau programme avec les mêmes réponses. Échoué : les leçons des unités `units_to_review` repassent « à faire ». Après C2 réussi : séances en conversation libre.
- **Attestation** : PDF (`AttestationPdf`) téléchargeable par son titulaire (`/attestations/{id}/telecharger`), vérifiable publiquement par son code (`/attestation/verifier/{code}`).
- **Gemini texte** (`TextClient`, `GEMINI_TEXT_MODEL`, défaut `gemini-3.5-flash-lite`) : présentation du programme et message d'encouragement aux inactifs. Toujours un texte type de repli si l'appel échoue.

### Mot de passe oublié (sans SMS ni e-mail)

- `/mot-de-passe-oublie` : explications + bouton WhatsApp avec un message contenant le numéro du compte.
- Fiche inscrit (`/admin/inscrits/{id}`) : « Générer un mot de passe provisoire » → affiché **une seule fois** (donnée flash), sessions de l'apprenant fermées, `must_change_password = true`.
- Le middleware `EnsurePasswordIsChanged` redirige alors vers `/mot-de-passe/nouveau` avant tout le reste (403 JSON pour les appels JavaScript).

### Conversation vocale (Gemini Live)

- Le navigateur se connecte **directement** à Google en WebSocket (`BidiGenerateContentConstrained`) avec un **jeton éphémère** à usage unique demandé par Laravel (`POST /{version}/auth_tokens`). La clé reste dans `.env`. Le jeton **fige** la configuration (modèle, consignes, fonctions) : le navigateur ne peut pas la changer.
- Audio : micro → PCM 16 bits mono 16 kHz (`audio/pcm;rate=16000`) ; voix reçue en PCM 16 bits 24 kHz. Sous-titres via `inputAudioTranscription` / `outputAudioTranscription`.
- **Temps imposé par le serveur** : une conversation se déroule d'un seul tenant ; le temps consommé = temps écoulé depuis le début, plafonné à `allowed_seconds`. Chaque jeton expire au plus tard à la fin autorisée (et au plus après `GEMINI_TOKEN_MINUTES`) : Google coupe alors la connexion. Le navigateur renouvelle le jeton avant expiration (et sur `goAway`) en reprenant la conversation (`sessionResumption`). Signal toutes les 15 s (`/signal`) ; sans signal, la conversation est close par `live:close-stale` (cron chaque minute), **décomptée jusqu'à l'expiration du dernier jeton**. Une seule séance ouverte à la fois ; refus de toute connexion quand le temps du jour est épuisé.
- Le professeur appelle des fonctions : `record_assessment_result(level, feedback)` (évaluation → `assessments` + `users.level`, une seule fois) et `end_lesson(summary, homework)`. Le navigateur les relaie à Laravel puis répond à Gemini. `set_expression(expression)` ne sort pas du navigateur (expression du professeur 3D).
- Les indications « début », « reprise » et « il reste N minutes » sont envoyées par le navigateur au bon moment (textes dans `config/teacher.php`, clé `cues`).

### Professeur 3D (phase 5)

- **Modèle** : « Teacher Joe », homme africain jeune adulte, lunettes, chemise blanche, cravate orange `#FF7A1A` et gilet bleu nuit `#0E2A5C`, assis à son bureau. Personnage **original** créé par `tools/teacher3d/build-teacher-joe.mjs` (aucune ressource tierce), licence **CC0 1.0** (domaine public), auteur : la plateforme. Poids : environ **300 Ko** (meshopt). Recherche faite : aucun modèle libre joignable ne réunissait homme africain + licence commerciale + visèmes + squelette + moins de 5 Mo.
- **Conventions** (pour pouvoir le remplacer) : os Mixamo, 15 visèmes Oculus, expressions ARKit. Changer de personnage = nouveau GLB dans `public/teacher3d/models/` + `config/avatar.php` (profil `generic` si le squelette n'est pas celui de Teacher Joe : pas de gestes des bras).
- **Bibliothèques** (statiques, regroupées en local et committées) : three.js 0.185.1 (MIT) et HeadAudio 0.1.0 (MIT, Mika Suominen). Crédits sur la page `/credits` (`config('avatar.credits')`).
- **Bouche** : HeadAudio (AudioWorklet, MFCC + modèle de visèmes) écoute la voix du professeur avant un retard de 80 ms ajouté au haut-parleur ; repli sur le volume et le timbre si HeadAudio échoue.
- **Comportement** (`teacher-3d.js`) : respiration, clignements, petits mouvements de tête au repos ; écoute (micro + sous-titres) : buste vers l'avant, tête penchée, hochements ; parole : gestes des mains et accents de la tête ; expressions `welcome`, `happy`, `encouraging`, `attentive`, `thinking`, `goodbye`, `neutral` demandées par Gemini via la fonction **`set_expression`** (déclarée après la fonction principale de chaque conversation quand `avatar.enabled`). Accueil automatique au début, au revoir à la fin.
- **Téléphone** : chargement en arrière-plan avec barre de progression ; le visage SVG reste affiché tant que la 3D n'est pas prête. Pas de 3D (visage SVG + lien « Afficher le professeur en 3D ») si : mode économie de données, préférence « Visage simple », moins de 1 Go de mémoire, pas de WebGL 2. Pendant l'affichage : 30 images/s au plus ; sous 20 images/s la définition baisse, sous 12 images/s retour automatique au SVG ; idem si le contexte WebGL est perdu. La séance n'est jamais bloquée.
- **Cache** : le modèle est gardé dans le Cache Storage du navigateur (cache `teacher3d`) ; le service worker du site (`public/sw.js`) garde les bibliothèques. Les URL portent une empreinte du contenu (`Avatar::asset()`), pas la date du fichier : rien n'est retéléchargé après un déploiement si le fichier n'a pas changé.
- Désactiver la 3D partout : `AVATAR_3D_ENABLED=false`.

### Identité visuelle (phase 6 bis : marque Talkami) et application installable (phase 6)

- **Charte** : `docs/charte-graphique.md` (couleurs, contrastes, police, logos, interdits). La lire avant tout changement visuel.
- **Couleurs** : bleu nuit `#0E2A5C` (principal : en-tête, titres, pied de page, liens), orange vif `#FF7A1A` (accent, boutons d'action), fond `#F4F6FB`. Nuances dérivées de ces deux couleurs uniquement. Toutes dans `public/css/tokens.css` (variables `--c-…`), contrastes AA en commentaire ; `app.css` n'a aucune couleur en dur (sauf la peau et les cheveux du visage SVG de secours). Règles : **texte bleu nuit sur les boutons orange, jamais blanc** (`--c-on-accent`) ; texte orange sur fond clair = `--c-accent-strong` `#B25512` (4,6:1). **Seul le bouton WhatsApp est vert** ; aucun autre vert (site, scène 3D, icônes). Pour l'application installée, reporter le bleu nuit et le fond dans `config/platform.php` (`brand.theme_color`, `brand.background_color`).
- **Police** : Sora (SIL Open Font License 1.1, The Sora Project Authors), une seule famille, woff2 variable réduit aux graisses 400 à 700 et aux caractères français (21 Ko), dans `public/fonts/`. Licence dans `public/fonts/OFL-Sora.txt` et sur la page Crédits.
- **Logos** : les quatre images fournies (jamais redessinées, ni en SVG ni en PDF), originaux dans `docs/brand/reference/`, fichiers du site dans `public/brand/` produits par `tools/brand/prepare-logos.py`. En-tête : logo horizontal ; pied de page : logo blanc (`config('platform.brand.logo')`). Favicon et icônes de l'application tirés de `icone`. Nom, nom court et accroche : `config/platform.php`. Ne jamais écrire le nom en dur. `tests/Feature/WebAppTest.php` vérifie que le nom vient de la configuration et que les fichiers des quatre logos existent.
- **Icônes** : `<x-icon>` (SVG intégrés, trait de 2 px, `currentColor`).
- **Animations** : courtes (`--dur…`), apparition des blocs `.reveal` (seulement si JavaScript est actif), `.rise`, boutons qui réagissent au toucher, glissement des étapes (`.step-panel`), barres de progression animées, transitions entre pages (`@view-transition`). Toutes coupées par « réduire les animations » (`prefers-reduced-motion`).
- **Fichiers versionnés** : `App\Support\Asset::url('css/app.css')` ajoute l'empreinte du contenu (`?v=…`). L'utiliser pour tout CSS, JS ou image référencé par une page.
- **Manifeste** : `/manifest.webmanifest` (`WebAppController`, sans session) : nom et nom court de la configuration, `display: standalone` (plein écran de l'application, sans barre d'adresse), `start_url: /espace`, icônes 192, 512 et adaptative 512, couleurs de l'écran de démarrage.
- **Service worker** (`public/sw.js`, ancien `teacher3d-sw.js` renommé et étendu, un seul pour tout le site) :
  - met en cache UNIQUEMENT les fichiers des dossiers `STATIC_PATHS` (`/css/`, `/js/`, `/fonts/`, `/icons/`, `/images/`, `/brand/`, `/teacher3d/`), versionnés (`?v=`) ou immuables (police, icônes) ; une seule version par fichier ; cache `static-v2` (changer le numéro supprime l'ancien cache à l'activation) ; le modèle 3D reste dans son propre cache `teacher3d` (géré par `teacher-3d.js`) ;
  - les pages (`mode: navigate`) passent TOUJOURS par le réseau, sans copie ; sans réseau, il affiche `/hors-connexion` (seule page gardée, sans session ni donnée personnelle) ;
  - n'intercepte jamais les envois (POST : paiements, signaux et bilans des séances) ni les réponses JSON (jetons des séances) ;
  - `tests/Feature/WebAppTest.php` vérifie qu'aucune route réservée aux comptes ne tombe dans un dossier mis en cache.
- **Installation** : bouton « Installer l'application » (Android, événement `beforeinstallprompt`) dans le pied de page et un bandeau discret sur l'accueil et l'espace apprenant ; sur iPhone, explication « Partager », puis « Sur l'écran d'accueil ». Fermeture mémorisée dans le navigateur.

### Routes (URL en français)

| URL | Nom | Accès |
|---|---|---|
| `/` | `home` | public |
| `/contact` | `contact` | public : coordonnées du centre |
| `/credits` | `credits` | public : licences et auteurs (professeur 3D, police, three.js, HeadAudio) |
| `/manifest.webmanifest` | `manifest` | public, sans session : manifeste de l'application installable |
| `/hors-connexion` | `offline` | public, sans session : page affichée par le service worker sans réseau |
| `/mot-de-passe-oublie` | `password.forgot` | invités |
| `/mot-de-passe/nouveau` (GET, PUT) | `password.change`, `password.update` | connectés (après mot de passe provisoire) |
| `/inscription` | `register` | invités |
| `/connexion` | `login` | invités (5 essais puis blocage temporaire) |
| `/deconnexion` (POST) | `logout` | connectés |
| `/espace` | `dashboard` | connectés |
| `/abonnement`, `/abonnement/{plan}` (GET, POST) | `payment.plans`, `payment.create`, `payment.store` | connectés |
| `/paiements/{payment}` | `payment.show` | le propriétaire du paiement |
| `/evaluation` (+ POST `/connexion`, `/signal`, `/resultat`, `/fin`) | `assessment.*` | connectés (une seule évaluation) |
| `/programme`, `/programme/questionnaire/{1-4}`, `/programme/unites/{n}` | `program.show`, `program.questionnaire(.store)`, `program.unit` | connectés (questionnaire : abonnement actif) |
| `/mes-seances` | `lessons.history` | historique, résumés, devoirs, erreurs en cours |
| `/bilan-niveau` (+ POST `/connexion`, `/signal`, `/resultat`, `/fin`) | `level-test.*` | programme terminé et abonnement actif |
| `/attestations/{attestation}/telecharger` | `attestation.download` | titulaire (ou administrateur) |
| `/attestation/verifier/{code}` | `attestation.verify` | public |
| `/seance` (+ POST `/connexion`, `/signal`, `/bilan`, `/fin`) | `lesson.*` | connectés avec abonnement actif |
| `/admin` → `/admin/paiements` | `admin.payments.pending` | administrateurs (403 sinon) : paiements en attente, Valider / Refuser |
| `/admin/paiements/historique` | `admin.payments.index` | filtres statut / pays / période, total encaissé du mois |
| `/admin/paiements/{payment}/capture` | `admin.payments.proof` | capture d'écran (stockage privé) |
| `/admin/inscrits`, `/admin/inscrits/{user}` | `admin.users.index`, `admin.users.show` | liste et fiche (mot de passe provisoire) |
| `/admin/consommation` | `admin.usage.index` | administrateurs : durée réelle, jetons, coût estimé par apprenant |
| `/admin/inactifs` (+ `/{user}/whatsapp`) | `admin.inactive.*` | abonnés sans séance depuis 3 jours ou plus ; bouton WhatsApp avec message rédigé |
| DELETE `/admin/inscrits/{user}/evaluation` | `admin.assessments.destroy` | administrateurs : permettre de repasser l'évaluation |

Il n'existe **aucun moyen de devenir administrateur depuis le site** : `php artisan app:create-admin`.

## Conventions de code

- **Code en anglais** (classes, méthodes, tables, colonnes, valeurs d'énumération), **tout ce que voit l'utilisateur en français** (vues, messages de validation, messages flash, libellés d'énumération via `label()`). Les commentaires sont en français.
- Style Laravel standard, vérifié par **Pint** (`vendor/bin/pint`). La CI échoue si le style n'est pas respecté.
- Contrôleurs fins ; validation dans des `FormRequest` ; logique métier réutilisable dans les modèles ou `app/Support`.
- Attributs Laravel 13 sur les modèles (`#[Fillable]`, `#[Hidden]`). `role` et `level` **ne sont jamais fillable**.
- Statuts et niveaux = **enums PHP** castés dans les modèles ; ne pas comparer à des chaînes brutes.
- Les montants sont des **entiers en FCFA** (pas de centimes, pas de float).
- Téléphones : toujours passer par `App\Support\PhoneNumber::normalize()` avant de stocker ou chercher.
- Mode strict Eloquent activé hors production (`Model::shouldBeStrict`) : charger les relations avec `with()`.
- Pas de dépendance front à compiler : `public/js/*.js` sont des modules ES écrits à la main (pas de SDK Google côté navigateur, le protocole WebSocket est utilisé directement). Si un jour une bibliothèque est nécessaire (personnage 3D), servir des modules ES depuis `public/` ou compiler **en local** et committer le résultat ; jamais de build sur le serveur.
- Appels à Gemini : uniquement via `Http` (facade) pour pouvoir les simuler dans les tests (`Http::fake`). Aucun test n'appelle la vraie API : `Http::preventStrayRequests()` est actif dans `tests/TestCase.php`. Pour un apprenant avec programme, utiliser le trait `Tests\Concerns\BuildsPrograms`.
- Vues : composants Blade anonymes (`<x-layout>`, `<x-input>`, `<x-icon>`, `<x-logo>`, `<x-stepper>`), CSS dans `public/css/app.css` avec les seules variables de `public/css/tokens.css`, mobile d'abord (les règles `@media (min-width: 640px)` puis `900px` élargissent). Durées affichées en toutes lettres (`App\Support\Duration`).
- Toute nouvelle fonctionnalité arrive avec ses tests (PHPUnit, `tests/Feature` et `tests/Unit`).

## Commandes utiles

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # données de démo (admin +22890000000 / password)
php artisan serve                 # http://localhost:8000
php artisan test                  # SQLite en mémoire (rapide)
DB_CONNECTION=mysql DB_DATABASE=… php artisan test   # contre MySQL, comme la CI
vendor/bin/pint                   # formatage
php artisan app:create-admin      # créer / promouvoir un administrateur
php artisan subscriptions:expire  # aussi lancée chaque heure par le planificateur
php artisan live:close-stale      # clôt les conversations finies/abandonnées (chaque minute par le planificateur)
cd tools/teacher3d && npm install && npm run model && npm run vendor   # EN LOCAL : régénère le modèle 3D et les bibliothèques
```

La CI (`.github/workflows/tests.yml`) lance Pint et toute la suite de tests contre **MySQL 8**.

## Déploiement sur Laravel Cloud (résumé)

1. Sur cloud.laravel.com, créer une application à partir du dépôt GitHub (branche `main`).
2. Dans l'environnement, attacher les ressources :
   - une base **MySQL** (les variables `DB_*` sont injectées automatiquement) ;
   - un **bucket Object Storage privé**, défini comme **disque par défaut** : il reçoit les captures de paiement (le disque du serveur est effacé à chaque déploiement).
3. Variables d'environnement : `APP_KEY` (générée en local avec `php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_TIMEZONE=Africa/Lome`, `APP_LOCALE=fr`, `GEMINI_API_KEY`, et les autres réglages de `.env.example` si besoin. Ne jamais committer ces valeurs.
4. Commande de build : `composer install --no-dev --optimize-autoloader` (aucune compilation front). Commande de déploiement : `php artisan migrate --force`.
5. Activer **Scheduler** sur le cluster App (lance `schedule:run` chaque minute), puis redéployer.
6. Après le premier déploiement, créer l'administrateur depuis la console Laravel Cloud : `php artisan app:create-admin`.
7. Le site est servi en **HTTPS** (obligatoire pour le micro).
