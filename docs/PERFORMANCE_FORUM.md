# Performance du forum GANXÔ (14 nov. 2026)

Objectif : alléger les pages publiques (visiteur non connecté) et supprimer le décalage de mise en page (CLS) des discussions, sans régression pour les membres.

## Ce qui a été fait

1. **Lecteur vidéo afterglow (419 Ko) chargé à la demande.** Il n'est plus dans `<head>` (`themes/default/apps/main/content.phtml`). Il est injecté par `gn_load_afterglow()` (`apps/main/scripts/app_master_script.phtml`) uniquement quand une `<video data-el="colibrism-video">` apparaît (stories / publication vidéo, y compris injectée en SPA), puis `afterglow.init()` est appelé. Les vidéos des publications du fil/discussions passent par fancybox et n'ont jamais utilisé afterglow.
2. **Feuille CSS allégée pour les visiteurs** : `themes/default/statics/css/visiteurs.purged.css` (≈ 306 Ko, ≈ 23 Ko gzip) remplace `animate.min.css` + `css-libs.css` + `master.styles.css` (645 Ko) quand `$cl["is_logged"]` est faux (`content.phtml`, repli automatique sur les 3 feuilles si le fichier est absent). Les membres connectés gardent les feuilles complètes. `ganho-forum.css` reste chargé pour tous.
3. **fancybox (JS 68 Ko + CSS 17 Ko) et audio-recorder (13 Ko)** : chargés d'emblée seulement pour les membres. Pour les visiteurs, fancybox est chargé à la première interaction avec une image de publication (survol, toucher, focus, ou clic : le clic est alors rejoué après chargement). audio-recorder (`Recorder`) n'est utilisé que par le composeur des membres.
4. **CLS 0,49 sur `/thread/908`** : l'image de publication (PNG de 2,4 Mo, `<img class="lozad" data-src=...>`) n'avait ni dimensions ni `src` ; elle s'affichait avec 0 px de haut puis poussait tout le contenu de 530 px quand elle arrivait. Correctif : helper `cl_media_dims_attr()` (`core/components/tools.php`, lit `getimagesize()` du fichier local, mis en cache par requête, vide si S3 ou fichier absent) appliqué aux images simples de `apps/thread/thread.phtml` et `apps/timeline/post.phtml` : il ajoute `width`/`height` et un `src` GIF transparent 1x1 (sans `src`, un `<img alt>` n'est pas un élément remplacé et `width/height` ne réservent rien). Règle CSS en fin de `themes/default/statics/ganho/css/ganho-forum.css` : `.publication-image img.lozad[width][height]{height:auto;max-width:100%}`. Rendu final inchangé (lozad remplace le `src` par `data-src`).
5. `.htaccess` : **non modifié**. Il contient déjà mod_deflate (CSS/JS/HTML) et mod_expires (CSS 1 mois, `ExpiresDefault` 190 jours donc JS/polices/images couverts) ; les URL sont versionnées (`?v=`), aucun en-tête supplémentaire n'était nécessaire.

## Mesures (laboratoire : mobile émulé 390 px, CPU x4, 4G simulée, SANS compression car `php -S`, passage chaud)

| Page | Poids avant | Poids après | FCP avant → après | LCP avant → après | CLS avant → après | TBT avant → après |
|---|---|---|---|---|---|---|
| /about_us | 1601 Ko | 755 Ko | 10,8 s → 6,1 s | 10,8 → 6,2 s | 0 → 0 | 1291 → 1336 ms |
| /faqs | 1601 | 755 | 10,6 → 5,9 | 10,6 → 5,9 | 0 → 0 | 1510 → 918 |
| /terms_of_use | 1601 | 755 | 11,3 → 6,2 | 11,3 → 6,2 | 0 → 0 | 1685 → 1269 |
| /privacy_policy | 1601 | 755 | 10,0 → 5,8 | 10,5 → 5,8 | 0 → 0 | 1205 → 490 |
| /cookies_policy | 1601 | 755 | 9,4 → 5,9 | 9,4 → 5,9 | 0 → 0 | 420 → 513 |
| /thread/908 | 1631 | 869 mesuré avec fancybox chargé d'emblée ; ≈ 785 estimé depuis le chargement à la 1re interaction (non remesuré) | 9,5 → 6,1 | 9,5 → 6,1 | **0,489 → 0** | 359 → 872 |
| /thread/904 | 1607 | 761 | 9,6 → 5,8 | 9,6 → 5,8 | 0 → 0 | 412 → 745 |
| /BESYP | 1938 | 1175 (dont 363 Ko d'images du fil) | 12,8 → 7,8 | 12,8 → 8,4 | 0 → 0 | 960 → 1915 |
| /guest (non modifiée) | 395 | 395 | 1,3 → 1,4 | 2,5 → 2,7 | 0 → 0 à 0,11 (bruit) | 310 → 353 |

Gzip estimé (Apache en production) : CSS visiteurs 23 Ko (contre ~50 Ko pour les 3 feuilles), afterglow (100 Ko gzip) n'est plus chargé.

Limites des mesures : le TBT est très variable d'un passage à l'autre sur cette machine (`php -S` mono-thread, CPU x4) ; il n'a PAS baissé de façon fiable et l'objectif « TBT < 300 ms » n'est pas atteint en laboratoire. Le poids n'est pas divisé par 2 sur toutes les pages (x2,1 sur les pages statiques, x1,9 sur /thread/908 au repos : le plancher restant est jQuery 87 Ko, Vue 91 Ko, Bootstrap/Popper 66 Ko, CSS 306 Ko, scripts maison). Aller plus loin demande de réécrire les très longues sélecteurs descendants de `master.styles.css` ou de retirer Vue/jQuery des pages publiques (hors périmètre, risque de régression).

## Régénérer la feuille visiteurs

À refaire après toute modification de `master.styles.css`, `css-libs.css`, `animate.min.css`, ou quand une classe de ces feuilles est utilisée dans une nouvelle vue/script. DEPUIS `forumganho/` (réseau requis pour `npx`) :

```
python docs/build_visiteurs_css.py
```

Le script fusionne les 3 feuilles dans `docs/.tmp-visiteurs/` (supprimé ensuite), lance PurgeCSS 6 (`docs/purgecss.visiteurs.config.cjs` : contenu = vues `.phtml`, JS maison, `core/**/*.php`, `apps/**/*.php`), puis clean-css (niveau 1) et écrit `themes/default/statics/css/visiteurs.purged.css`. Si la feuille cause un défaut visuel pour les visiteurs, supprimer simplement ce fichier : `content.phtml` retombe sur les 3 feuilles d'origine.

## Limites / points d'attention

- La safelist de la config conserve les classes d'état (modal, dropdown, show, active, animate, fancybox...) et seulement les classes `cl-app-<page>` des pages visibles par un visiteur (guest, profile, thread, search, trending, stat_pages, suggested, confirm_*, events, educational, comics, alert, suggest, err404/500). Les règles du composeur (`pubbox`) et des réglages d'affichage sont exclues (rendus uniquement pour les membres). Si une page réservée aux membres devenait publique, retirer ce filtrage ou l'ajouter à la safelist, puis régénérer.
- Les classes construites dynamiquement dans des scripts (concaténation de chaînes) ne sont pas détectées par PurgeCSS.
- `afterglow` : tout nouveau code qui appelle `afterglow.*` doit passer par `gn_load_afterglow()` (ou tester `window.afterglow`).
- Non vérifié : appareil réel, Apache avec mod_deflate, effet du cache navigateur en production.
