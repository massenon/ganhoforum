# Simulateur mobile du forum GANXÔ

Maquette interactive, statique et autonome de l'application mobile du forum GANXÔ : un seul fichier `index.html` (HTML, CSS et JavaScript inline, sans framework ni CDN, 118 Ko) et un dossier `assets/` (images, logos, police Montserrat locale). **Tout est en français. Tout est simulé : contenu fictif, aucune donnée réelle, aucun appel réseau.**

**Elle reprend le design du forum web en version mobile** (`forumganho/`, tel qu'affiché à 390 px) : mêmes jetons (`--cl-*`, `--gn-*`), en-tête (titre de page + logo GANXÔ) sous le filet arc-en-ciel, barre de navigation basse flottante sombre arrondie (Menu « … », Fil d'actualité, Explorer, Notifications, Messages), tiroir « … », pastilles de filtres avec compteurs, cartes de fiches et de BD (« À la une », « 5 cases », « Avec bulles de conversation »), lecteur de BD avec bulles dit / pense / ressent, page invité (onglets noirs Connexion / Inscription), alerte VBG en chat avec l'« Assistant GANXÔ », profil. Clair et nuit (`html[data-bg=dark]`, mêmes couleurs que `body[data-bg=dark]` du forum).

## Ouvrir la maquette

Double-cliquer sur `index.html` (ou servir le dossier : `python -m http.server 8010`). Sur grand écran, l'application s'affiche dans un cadre de téléphone de 390 x 844 px ; sous 480 px de large, elle occupe tout l'écran. Aucune barre de défilement n'est visible (le défilement au doigt ou à la molette reste possible).

## Écrans

1. **Splash** (2,4 s) : fond nuit, logo, bande arc-en-ciel animée, « Forum ».
2. **Carrousel d'accueil** (4 diapositives, une photo chacune), glissement, points, « Passer » / « Suivant » / « Commencer », flèches du clavier, annonce vocale.
3. **Page invité** (comme `/guest`) : onglets Connexion / Inscription, « Rester connecté·e », « Mot de passe oublié ? », lien « Créer une alerte VBG » sans compte, bandeau cookies (« Accepter »). Tient dans un seul écran sans défilement (390 x 844 et 360 x 640).
4. **Fil d'actualité** : « Bonjour Aube, Exprimez-vous ? », puces Photo / Sondage / Témoignage / Événement, outils, « Publier » (ajoute un message en tête), histoires (« Ma story »), publications avec réaction.
5. **Tiroir « … »** : Fil d'actualité, Notifications, Thématiques, Explorer, Messages, Favoris, Créer une alerte VBG, Contenus éducatifs, Bandes dessinées, Mode nuit, liens légaux, « Se déconnecter ».
6. **Contenus éducatifs** : introduction, 7 catégories filtrables avec compteurs, 21 fiches (textes du forum, résumés) ; page de fiche avec encadré « À retenir » et appel à créer une alerte.
7. **Bandes dessinées** : 4 BD ; lecteur « Case par case » / « Défilement », couches Ce qu'on dit / pense / ressent, « Révéler pas à pas », « Comprendre les codes couleur », bulles de conversation (formes, couleurs, pictogrammes, nom de l'émotion) repositionnées pour rester dans l'image.
8. **Créer une alerte VBG** (chat de l'Assistant GANXÔ) : catégorie, description (10 caractères minimum), contact facultatif (e-mail, téléphone ou anonyme), résumé avec consentement, confirmation (numéro fictif), suivi. **SOS à maintenir 2 s** : prépare l'alerte, ne contacte aucun service d'urgence.
9. **Profil** (pseudonyme fictif) : cover, statistiques, onglets, « Paramètres de profil » (mode nuit, aperçus de notification masqués, code PIN, mode discret, déconnexion, suppression du compte avec confirmation).
10. **Explorer, Notifications, Messages** : listes simples (contenu fictif).

## Images

| Fichier | Source |
|---|---|
| `assets/slide-1.webp` (main tenant un drapeau arc-en-ciel) | Pexels, Anete Lusina (5721337) |
| `assets/slide-2.webp` (jeune homme de profil, drapeau sur les épaules) | Pexels, Anete Lusina (5721326) |
| `assets/slide-3.webp` (personne de dos aux cheveux bouclés, coucher de soleil) | Pexels, vkmoraesph (13892449) |
| `assets/slide-4.webp` (personne aux tresses roses, fresque murale) | Pexels, Christian Araujo (32858602) |
| `assets/bd1..bd4-1..5.webp` | Illustrations de BD du forum (`forumganho/.../img/bd/`), réduites à 800 px |
| `assets/logo-*.png`, `Montserrat-latin-normal.woff2` | Design system Ganho |

## Ce qui est simulé et limites

- Connexion, inscription, publications, réactions, envoi d'alerte et numéro de suivi, réponses de l'équipe, SOS, code PIN, mode discret, suppression : rien n'est enregistré ni envoyé (seul le choix clair / nuit est mémorisé, `localStorage` en try/catch).
- Les textes de BD, de bulles et de fiches viennent du forum (contenu éditorial, personnages fictifs) ; les fiches sont raccourcies (début de fiche et « À retenir ») pour tenir dans un seul fichier. Les pseudonymes et publications du fil sont inventés ; aucune donnée du forum local n'a été copiée.
- Écarts connus avec le forum web : navigation basse sans libellés (comme le forum, avec `aria-label`) ; Favoris, Médias, J'aime, sondages, photos, histoires : simulés ou non fonctionnels ; l'alerte ajoute un consentement explicite et le bouton SOS (absents du chat du forum) ; chips de filtre à 44 px minimum (cible tactile) donc un peu plus hautes que sur le forum ; sur 360 x 640 le journal du chat défile en interne (sans barre visible).
- Contrastes visés WCAG AA, non mesurés automatiquement.

## Captures

`captures/` : 29 captures 390 x 844 en WebP (splash, carrousel, invité, fil, tiroir, éducation et fiche, BD et lecteur, alerte à chaque étape, SOS, profil, mode nuit).
