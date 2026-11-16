# API mobile du forum : addendum v1.1 (contrat pour l'application Flutter)

Base : `https://forum.ganho.app/api/mobile/v1` (la version d'URL reste `v1` : tout est **additif**, rien n'est cassé).
Conventions inchangées (voir `API_FORUM_MOBILE.md` §1) : `Authorization: Bearer <jeton>`, JSON UTF-8, réponses `{ "data": …, "meta": … }`,
erreurs `{ "error": "code", "message": "texte français", "errors": { "champ": ["…"] } }`, jamais d'e-mail ni d'adresse IP en sortie.
Tous les exemples ci-dessous viennent de vrais appels contre la base locale (identifiants raccourcis, comptes de test jetables supprimés ensuite).

Routes nouvelles (✱ = jeton requis ; toutes le sont) :

| Méthode et chemin | Rôle |
|---|---|
| `POST posts/{id}/report` | Signaler une publication |
| `POST users/{id}/report` | Signaler un compte |
| `POST users/{id}/block` | Bloquer un compte |
| `DELETE users/{id}/block` | Débloquer |
| `GET me/blocks` | Liste de mes blocages |
| `DELETE me` ou `POST me/delete` | Supprimer mon compte (mot de passe obligatoire) |
| `GET users/{id}` | Profil public |
| `GET users/{id}/posts?tab=posts\|media\|likes` | Publications d'un profil |
| `GET stories` | Stories des comptes suivis |
| `GET search?q=&type=posts\|people` | Recherche |

Modifiées : `GET feed`, `GET posts/{id}`, `GET posts/{id}/replies`, `POST posts`, `GET notifications`, `GET education`, `GET education/{slug}`, `GET comics`, `GET comics/{slug}` (voir §8).

## 1. Signalement

`POST posts/{id}/report` et `POST users/{id}/report`, corps :

```json
{ "reason": "harassment", "details": "Messages insistants" }
```

`reason` (liste fermée) : `harassment` (harcèlement), `hate` (haine), `violence`, `sexual` (contenu sexuel), `spam`, `impersonation` (usurpation), `other` (autre). `details` facultatif, 500 caractères maximum.

Le signalement alimente la file « Modération » de l'admin du forum (`/admin_panel/moderation_queue`), avec le motif en tête du commentaire : `[Harcèlement] Messages insistants`.
La publication signalée **disparaît du fil de la personne qui a signalé** (comportement du web). Idempotent par (acteur, cible).

Première fois, **201** :
```json
{ "data": { "reported": true, "already_reported": false } }
```
Déjà signalé, **200** (aucune nouvelle ligne) :
```json
{ "data": { "reported": true, "already_reported": true } }
```

Erreurs :
- 422 `validation_failed` : `{"error":"validation_failed","message":"Vérifiez les champs signalés.","errors":{"reason":["Choisissez un motif : harassment, hate, violence, sexual, spam, impersonation, other."]}}` (ou `details` : « 500 caractères maximum. »)
- 403 `cannot_target_self` : `{"error":"cannot_target_self","message":"Vous ne pouvez pas signaler votre propre publication."}`
- 404 `not_found` : publication ou compte inconnu, ou publication non visible par la personne
- 401 `unauthenticated`, 429 `too_many_requests` (10 signalements / h par compte, 40 / h par IP ; `Retry-After: 3600`)

## 2. Blocage

`POST users/{id}/block` → **200** `{ "data": { "blocked": true } }` (idempotent : bloquer deux fois = même réponse). Retire aussi les abonnements dans les deux sens (comme le web).
`DELETE users/{id}/block` → **200** `{ "data": { "blocked": false } }` (idempotent).
Erreurs : 403 `cannot_target_self` (« Vous ne pouvez pas vous bloquer vous-même. »), 404 `not_found` (« Compte introuvable. »), 429 (40 / h par compte).

`GET me/blocks?limit=30&before=<blocked_id>` :
```json
{ "data": [ { "id": 234, "username": "mvtestb", "name": "Jetable B", "avatar": "http://…/avatar-1.png", "verified": false, "blocked_at": 1794827068 } ],
  "meta": { "limit": 30, "has_more": false, "next_before": null } }
```

Effet du blocage, **dans les deux sens** (celui qui bloque et celui qui est bloqué) : le fil, les réponses d'une publication, les notifications, la recherche (publications et personnes), les stories et les profils excluent l'autre personne ; `GET posts/{id}` (publication de l'autre), `GET users/{id}`, `GET users/{id}/posts`, `POST posts/{id}/like` répondent **404**.
Le bloqué ne reçoit aucun signe qu'il est bloqué (même 404 qu'un compte inexistant).

## 3. Suppression de compte

`DELETE me` (ou `POST me/delete` si le client HTTP n'envoie pas de corps avec DELETE), corps JSON :
```json
{ "password": "le mot de passe actuel" }
```
**200** : `{ "data": { "deleted": true } }`. Ensuite le jeton répond **401** `{"error":"unauthenticated","message":"Session expirée. Reconnectez-vous."}` et la connexion échoue (`invalid_credentials`). L'application efface tout son stockage local.

Même routine que « Paramètres > Supprimer mon compte » du web : compte, publications, réponses, « j'aime », abonnements, messages privés, signets, notifications, signalements faits, blocages, sessions web. En plus : jetons, appareils et suivi d'alertes (`cl_mobile_*`) de ce compte et de ses appareils.
URL web publique (sans connexion) expliquant la procédure, exigée par Google Play : `https://forum.ganho.app/supprimer-mon-compte`.

**Les alertes déjà remises à ganhovbg restent côté association** (traitées selon la durée de conservation de la politique de confidentialité).

Erreurs : 422 `validation_failed` (`{"error":"validation_failed","message":"Mot de passe incorrect.","errors":{"password":["Mot de passe incorrect."]}}`, aussi quand le champ est absent) ;
403 `admin_account` (les comptes administrateur se suppriment depuis le panneau web) ; 429 (5 essais / h par compte, 10 / h par IP, **compté avant** la vérification du mot de passe : pas de force brute ; `Retry-After: 3600`) ; 401.
Le journal serveur n'écrit que `[mobile_v1] account_deleted` (aucun identifiant, pseudo ou e-mail).

## 4. Profil public

`GET users/{id}` → **200** :
```json
{ "data": { "id": 234, "username": "mvtestb", "name": "Jetable B", "avatar": "http://…/avatar-1.png", "verified": false,
  "about": "", "cover": "http://…/cover-1.png",
  "counts": { "posts": 1, "followers": 0, "following": 0 }, "joined_at": 1794826966,
  "is_me": false, "is_following": false, "follows_me": false, "tabs": ["posts", "media", "likes"] } }
```
**404** `{"error":"not_found","message":"Compte introuvable."}` si : compte inconnu ou inactif, **bloqué dans un sens ou dans l'autre**, ou profil privé (`profile_privacy = followers` et vous ne le suivez pas). Aucune différence entre ces cas.
L'e-mail et l'IP ne sont jamais renvoyés. (Le suivi/désabonnement n'existe pas encore dans l'API : `is_following` est en lecture seule.)

`GET users/{id}/posts?tab=posts&limit=20&before=<next_before>` (`tab` : `posts` par défaut, `media` = publications avec image/vidéo/gif/audio, `likes` = publications aimées ; `limit` 1 à 50) :
```json
{ "data": [ { "id": 847, "type": "image", "…": "(objet publication, voir §7)" } ],
  "meta": { "tab": "posts", "limit": 2, "has_more": true, "next_before": 328 } }
```
`next_before` est un **curseur opaque** (identifiant de ligne du fil / des « j'aime », pas l'id de la publication) : le renvoyer tel quel dans `before`.
Onglet inconnu : 422 `{"errors":{"tab":["Valeurs : posts, media, likes."]}}`. Le web n'a pas d'onglet « réponses » (les réponses n'apparaissent pas dans les publications d'un profil) : il n'existe pas ici non plus.

## 5. Stories

`GET stories` → stories actives (« swifts » du web) des comptes suivis et de soi-même, expirées exclues, comptes bloqués exclus :
```json
{ "data": [ { "user": { "id": 234, "username": "mvtestb", "name": "Jetable B", "avatar": "http://…", "verified": false },
    "is_me": false, "has_unseen": true,
    "items": [ { "id": "abc123", "type": "image", "media_url": "http://…/cover-1.png", "text": "Coucou story", "expires_at": 1794834292, "seen": false } ] } ],
  "meta": { "enabled": true, "total": 1 } }
```
Si le réglage `swift_system_status` du forum est désactivé : `{"data":[],"meta":{"enabled":false}}`. `type` : `image` ou `video` (`media_url` est alors le fichier). **Lecture seule** : marquer « vue » et publier une story ne sont pas dans l'API.

## 6. Recherche

`GET search?q=<2 à 80 caractères>&type=posts|people&limit=15&before=<next_before>` (`type` : `posts` par défaut).
`people` : `[{ "id", "username", "name", "avatar", "verified", "about" }]`. `posts` : objets publication (§7). Meta : `{ "type", "limit", "has_more", "next_before" }`.
Erreurs : 422 (`q` trop court : « Saisissez au moins 2 caractères. » ; `type` inconnu), 429 (60 / 5 min par IP). Les comptes bloqués (dans les deux sens) sont exclus.

## 7. Objet publication (feed, posts, replies, users/{id}/posts, search)

```json
{ "id": 340, "type": "text", "text": "…", "created_at": 1716459519,
  "author": { "id": 10, "username": "…", "name": "…", "avatar": "http://…", "verified": false },
  "likes": 5, "replies": 0, "reposts": 0, "liked": false, "mine": false, "thread_id": null,
  "media": [ { "type": "image", "url": "http://…" } ],
  "poll": null,
  "link": { "url": "https://…", "title": "…", "description": "…", "image_url": "http://…" },
  "url": "http://…/thread/340" }
```
Champs ajoutés par rapport à la v1 : `reposts`, `thread_id` (null si publication racine), `poll`, `link`, `url`, `media[].poster_url` (vidéos).

- **`type`** (ce que distingue le web) : `text`, `image`, `video`, `gif`, `audio`, `poll`. Un lien enrichi n'est pas un type à part : c'est un `text` avec `link` renseigné.
- **`poll`** (si `type = poll`) : `{ "total": 3, "has_voted": false, "options": [ { "option": "…", "votes": 2, "percentage": 67 } ] }` ; voter n'est pas dans l'API (lecture seule).
- **`url`** : adresse publique canonique `/thread/{id}`, à utiliser pour partager. `null` quand la publication n'est pas publique (visibilité abonnés / mentionnés) ou n'est pas active : dans ce cas ne proposer aucun partage.
- **Réactions** : le web n'a que le « j'aime » (`POST posts/{id}/like`, qui bascule : `{ "liked": true, "likes": 1 }`). Il n'y a donc **pas** de route `react`.
- Pagination `before` du **fil** : c'était l'id de la publication (incorrect, les deux séquences d'identifiants diffèrent) ; c'est désormais l'identifiant de ligne du fil renvoyé dans `meta.next_before` : toujours renvoyer `next_before` tel quel.
- `GET notifications` : `created_at` est maintenant renseigné (il valait `null`), `seen` aussi, et `actor.id` est ajouté.

## 8. Éducation et bandes dessinées : « à la une »

`GET education` et `GET comics` : chaque élément porte `"featured": true|false` (= fiche **épinglée** dans l'admin ; `pinned` est conservé, même valeur). L'ordre renvoyé est celui du web : épinglées d'abord, puis les plus récemment modifiées. `meta.featured_count` donne leur nombre. Les détails (`education/{slug}`, `comics/{slug}`) portent aussi `featured` et `pinned`.
Exemple : `GET education` → `meta: { "total": 21, "featured_count": 5, "categories": { … } }`, premier élément `{ "slug": "deepfakes-et-manipulation-d-images-par-ia", …, "pinned": true, "featured": true }`.

## 9. Limites de débit (nouvelles)

| Action | Limite |
|---|---|
| Signalement | 10 / h par compte, 40 / h par IP |
| Blocage / déblocage | 40 / h par compte |
| Suppression de compte | 5 / h par compte, 10 / h par IP (avant vérification du mot de passe) |
| Recherche | 60 / 5 min par IP |

Rappel : 300 requêtes / 5 min par acteur pour toute l'API ; dépassement = 429 + `Retry-After`.

## 10. Codes d'erreur ajoutés

`cannot_target_self` (403), `admin_account` (403). Les autres (`validation_failed`, `not_found`, `unauthenticated`, `too_many_requests`) sont inchangés.
