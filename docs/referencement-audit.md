# Audit du référencement de talkami.net (29 octobre 2026)

Relevé fait sur le site EN LIGNE (requêtes comme Googlebot), puis dans le code. Aucune promesse de classement : Google décide seul, et le temps compte beaucoup.

## 1. Ce qui est vérifié et correct

| Point | Constat |
|---|---|
| robots.txt | 200, `Allow: /`, seules les pages privées sont fermées (`/admin`, `/espace`, `/seance`…), ligne `Sitemap:` vers talkami.net. |
| sitemap.xml | 200, 25 adresses, toutes sur `https://talkami.net/`. |
| Pages du sitemap | Les 25 répondent 200, `<meta name="robots" content="index, follow">`, aucun en-tête `X-Robots-Tag`, canonique = leur propre adresse. |
| Contenu | Texte écrit dans la page par le serveur (pas seulement par JavaScript) : accueil environ 3 500 mots, pages pays environ 1 150, guides 650 à 1 150. Aucune page « mince » parmi celles qui sont indexées (Contact : environ 330 mots, normal pour ce type de page). |
| Titres et descriptions | Uniques ; accueil : « Talkami · Cours d'anglais en ligne avec Teacher Joe ». `lang="fr"`, un seul `<h1>`. |
| Données structurées | Accueil : Organization (nom, adresse, logo), WebSite, Service avec les vrais prix, FAQPage ; « À propos » : Person ; guides : Article et fil d'Ariane. |
| Ancienne adresse (#97) | `talkami.laravel.cloud` → 301 vers `talkami.net` (même chemin) ; `www.talkami.net` → 301 vers `talkami.net`. |
| Pages pays | Togo, Bénin, Burkina Faso, Côte d'Ivoire : indexables, textes différents, FAQPage. |
| Vitesse sur téléphone | Accueil : 17 Ko compressé (gzip), réponse en environ 0,6 seconde ; une seule police, aucune bibliothèque JavaScript. |

**Aucun blocage d'indexation trouvé** : ni noindex, ni robots.txt fermé, ni contenu caché derrière JavaScript, ni canonique vers une autre adresse.

## 2. Pourquoi « Talkami » n'apparaît pas encore (le plus probable)

1. **Le domaine est neuf** : talkami.net est l'adresse officielle depuis le 27 octobre 2026, il y a deux jours. Google doit d'abord découvrir le domaine, l'explorer, puis l'indexer : cela prend en général de quelques jours à quelques semaines, plus longtemps sans liens extérieurs.
2. **Aucune vérification Search Console visible** : la page d'accueil n'a pas de balise `google-site-verification` (variable `GOOGLE_SITE_VERIFICATION` vide). Si la propriété n'a pas été vérifiée autrement (DNS chez Cloudflare), Google ne connaît peut-être pas encore le sitemap.
3. **Peu de liens extérieurs** : Google trouve et classe un nouveau site surtout grâce aux liens qui viennent d'ailleurs (réseaux, annuaires, fiche d'entreprise).
4. **En anglais** : le site est entièrement en français, pour des francophones. Ne pas apparaître dans les 100 premiers résultats d'une recherche en anglais est normal et ne changera pas sans pages en anglais (non proposé).

## 3. Corrigé dans le code (sans risque)

- Données structurées WebSite : `@id` et `alternateName` (le nom de domaine, « talkami.net »), comme le recommande Google pour le nom de site affiché dans les résultats.

## 4. Proposé, NON codé (à décider)

- Mettre les liens TikTok et chaîne WhatsApp (`sameAs`) aussi dans l'Organization de l'ACCUEIL (aujourd'hui seulement sur « À propos », par décision passée : liens affichés seulement là).
- Une courte phrase « Talkami, c'est … » visible en haut de l'accueil avec le nom écrit en texte (aujourd'hui le nom est surtout dans le logo et le titre), pour les recherches de marque.
- Plus tard, quand Search Console montrera les recherches réelles : nouveaux guides sur les questions vraiment tapées.

## 5. À faire par vous, hors du site

1. **Google Search Console** (le plus important) : ajouter la propriété « Domaine » `talkami.net` (vérification par un enregistrement TXT chez Cloudflare) ou « Préfixe d'URL » `https://talkami.net/` (balise : coller la valeur dans `GOOGLE_SITE_VERIFICATION` sur Laravel Cloud, puis redéployer). Puis « Sitemaps » → envoyer `sitemap.xml` ; « Inspection de l'URL » → `https://talkami.net/` → « Demander l'indexation ». Sur l'ancienne propriété talkami.laravel.cloud (si elle existe) : « Changement d'adresse » vers talkami.net.
2. **Fiche d'établissement Google** (Google Business Profile) : nom « Talkami », catégorie école de langues, adresse du bureau à Lomé, téléphone, site `https://talkami.net/`. Elle aide beaucoup pour les recherches de marque et à Lomé.
3. **Liens vers talkami.net** : dans la bio TikTok, la description de la chaîne WhatsApp, une page Facebook, une chaîne YouTube (description de chaque vidéo), LinkedIn du fondateur. Toujours l'adresse complète `https://talkami.net/`.
4. **Annuaires** : annuaires d'entreprises et d'écoles du Togo, pages d'associations ou d'écoles partenaires qui acceptent un lien.
5. **Bing Webmaster Tools** : importer la propriété depuis Search Console (quelques clics).

## 6. Ce qui dépend du temps et de la notoriété

L'indexation des 25 pages, l'apparition sur la recherche « Talkami » et surtout la position sur des recherches générales (« cours d'anglais en ligne ») dépendent du temps, du nombre de liens extérieurs et du nombre de personnes qui cherchent et cliquent. Le code ne peut pas les forcer. Compter plusieurs semaines pour la marque, plusieurs mois pour les recherches générales, sans aucune garantie.
