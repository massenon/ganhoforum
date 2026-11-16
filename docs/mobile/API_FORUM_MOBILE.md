# API mobile du forum GANXÔ : v1 (+ v1.1)

> **Statut (14 novembre 2026) : livrée côté serveur et testée en local contre les bases locales** (`mobile_v1.php`,
> `core/apps/mobile_v1/`). **Aucune application mobile ne l'appelle encore, et elle n'a pas été déployée en production.**
> **Addendum v1.1 (16 novembre 2026)** : signalement, blocage, suppression de compte, profils publics, stories, recherche, « à la une » ; contrat détaillé avec exemples dans `API_FORUM_MOBILE_V1_1.md`.
> Ce document décrit ce qui est implémenté, pas une proposition. Le contrat côté « alerte » s'appuie sur l'API serveur à serveur
> de ganhovbg (`/api/forum/*`) ; la future API mobile de ganhovbg est décrite dans `ganhovbg/docs/mobile/API_MOBILE.md`.

Base : `https://forum.ganho.app/api/mobile/v1` · JSON UTF-8 · textes d'erreur en français · HTTPS seul en production.

L'API historique du script (`/mobile_api/*`, `/native_api/*`) est **laissée telle quelle** : elle accepte `session_id` dans l'URL,
renvoie l'e-mail, l'adresse IP et le portefeuille de la personne et n'a ni limitation de débit ni messages français. La v1 la remplace pour le mobile.

## 1. Conventions

| Sujet | Règle |
|---|---|
| Authentification | `Authorization: Bearer <jeton>` (64 caractères hexadécimaux). **Aucun cookie, aucun `session_id`** n'est lu : un navigateur ne peut pas authentifier une requête mobile (pas de CSRF). |
| Jeton | Tiré de `random_bytes(32)`, **seul son hachage SHA-256 est stocké** (`cl_mobile_tokens`). Durée 30 jours glissants, révocable. |
| Appareil | `X-Device-Id: <uuid>` (16 à 64 caractères hexadécimaux et tirets). Obligatoire pour les alertes sans compte et pour le jeton de notification. |
| Idempotence | `Idempotency-Key: <8 à 64 caractères>` sur `POST posts` et `POST alerts…` : même clé + même route + même acteur = même réponse (en-tête `Idempotent-Replay: true`), sans second effet. Conservée 24 h. |
| Corps | JSON (`Content-Type: application/json`) ou formulaire ; 64 Ko maximum (413 au-delà). |
| Pagination | `?limit=` (1 à 50) et `?before=<id>` ; la réponse porte `meta.has_more` et `meta.next_before`. |
| Cache | Toutes les réponses : `Cache-Control: no-store, private`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`. |
| Limitation de débit | 300 requêtes / 5 min par acteur, plus des limites par action (§3). Dépassement : 429 + `Retry-After`. |

Réponse réussie : `{ "data": …, "meta": … }`. Erreur : `{ "error": "code", "message": "texte français", "errors": { "champ": ["…"] } }`.

| Statut | `error` | Sens pour l'application |
|---|---|---|
| 400 | `invalid_json`, `invalid_idempotency_key` | Requête mal formée |
| 401 | `unauthenticated` | Jeton absent, expiré ou révoqué : purge locale, retour à la connexion |
| 401 | `invalid_credentials` | Identifiants incorrects (même réponse que le compte existe ou non) |
| 403 | `device_required` | Envoyer `X-Device-Id` / enregistrer l'appareil |
| 403 | `device_revoked` | Appareil révoqué : **effacement local complet** |
| 403 | `account_inactive` | Compte désactivé ou en attente d'activation |
| 404 | `not_found` | Ressource absente, privée ou hors périmètre (jamais de différence entre les trois) |
| 405 | `method_not_allowed` | Méthode non prévue pour cette route |
| 413 | `payload_too_large` | Corps trop gros |
| 403 | `cannot_target_self`, `admin_account` | Signaler/bloquer soi-même ; compte administrateur (se supprime depuis le web) |
| 422 | `validation_failed`, `banned_word` | Erreurs par champ ; mot interdit par la modération |
| 429 | `too_many_requests` | Attendre `Retry-After` |
| 502 / 503 | `upstream_error`, `alerts_unavailable` | Le service d'alerte de ganho.app ne répond pas |
| 500 | `server_error` | Erreur interne (détail dans le journal du serveur, jamais dans la réponse) |

## 2. Routes

✱ = jeton requis.

| Méthode et chemin | Rôle | Réutilise |
|---|---|---|
| `GET status` | Santé, version d'API, maintenance, version minimale | — |
| `POST devices` | Enregistrer l'appareil `{device_id, platform: android\|ios, app_version}` | table `cl_mobile_devices` |
| `DELETE devices/{id}` ✱ | Révoquer un appareil **de son propre compte** (révoque aussi ses jetons) | — |
| `POST auth/login` | `{identifier (e-mail ou pseudo), password, device_id, platform}` → `{token, expires_at, user}` | `password_verify` sur `cl_users` |
| `POST auth/logout` ✱ | Révoque le jeton courant | — |
| `GET me` ✱ | Profil minimal : id, pseudo, nom, avatar, vérifié, présentation, `spaces`, compteurs | `cl_user_data` |
| `POST me/push-token` ✱ | `{token}` FCM, rattaché à l'appareil (`X-Device-Id`) | — |
| `GET feed` ✱ | Fil d'actualité | `cl_get_timeline_feed` |
| `GET posts/{id}` ✱, `GET posts/{id}/replies` ✱ | Publication et réponses (404 si elle n'est pas visible par la personne) | `cl_get_thread_data`, `cl_get_thread_child_posts` |
| `POST posts` ✱ | Publier du texte `{text, thread_id?, privacy?}` ; mots interdits de la modération appliqués | logique de `publish_post` + `cl_adm_blocked_word` |
| `POST posts/{id}/like` ✱ | Bascule le « j'aime » → `{liked, likes}` | logique de `like_post` |
| `GET notifications` ✱ | `?type=notifs\|mentions` | `cl_get_notifications` |
| `GET education`, `GET education/{slug}` | Fiches éducatives publiées (`?category=`), champ `featured` (= épinglée), épinglées en premier ; détail avec `body_source`, `body_html`, `toc` | `core/apps/education` |
| `GET comics`, `GET comics/{slug}` | Bandes dessinées ; détail avec cases : `image_url`, `alt`, `caption`, `dialogue`, **`bubbles`** (dit / pense / ressent, couleur, position en %) | idem |
| `POST posts/{id}/report` ✱, `POST users/{id}/report` ✱ | Signaler `{reason: harassment\|hate\|violence\|sexual\|spam\|impersonation\|other, details? ≤ 500}` ; idempotent ; ajoute une ligne à la file « Modération » de l'admin | tables `cl_pub_reports` / `cl_profile_reports` |
| `POST users/{id}/block` ✱, `DELETE users/{id}/block` ✱, `GET me/blocks` ✱ | Bloquer / débloquer / lister ; exclusion dans les deux sens (fil, réponses, notifications, recherche, stories, profils) | table `cl_blocks`, logique de `block_user` |
| `DELETE me` ✱ ou `POST me/delete` ✱ | Suppression de compte `{password}` ; purge aussi `cl_mobile_tokens`, `cl_mobile_devices`, `cl_mobile_alerts` | `cl_delete_user_data` (comme `/settings/delete`) |
| `GET users/{id}` ✱, `GET users/{id}/posts` ✱ | Profil public ; publications `?tab=posts\|media\|likes` ; 404 si bloqué ou privé | `cl_can_view_profile`, `cl_get_profile_posts`, `cl_get_profile_likes` |
| `GET stories` ✱ | Stories actives des comptes suivis | `cl_timeline_swifts` |
| `GET search?q=&type=posts\|people` ✱ | Recherche | `cl_search_posts`, `cl_search_people` |
| `GET alerts/config` | Catégories et bornes (`subject_max`, `desc_min`, `desc_max`) | API ganhovbg `/forum/alert-config` |
| `POST alerts` | Créer une alerte `{category, message, email?, phone?}` (compte **non requis**, `X-Device-Id` requis) | API ganhovbg `/forum/alerts` |
| `GET alerts` | Mes alertes (celles de ce compte ET de cet appareil) | table `cl_mobile_alerts` |
| `GET alerts/{ticket}/messages`, `POST alerts/{ticket}/messages` | Lire et répondre (`{comment}`) ; **404 si l'alerte n'appartient pas à ce compte ou à cet appareil** | API ganhovbg |

Rendus de texte : le texte d'une publication est renvoyé en **texte brut** (balises retirées, entités décodées), pas en HTML.

## 3. Limites par action

| Action | Limite |
|---|---|
| Connexion | 30 / 15 min par IP et 8 / 15 min par identifiant |
| Enregistrement d'appareil | 30 / h par IP |
| Publication | 20 / 10 min par compte |
| « J'aime » | 120 / 10 min par compte |
| Création d'alerte | 5 / h par compte ou appareil, 15 / h par IP |
| Réponse à une alerte | 30 / h |
| Signalement (v1.1) | 10 / h par compte, 40 / h par IP |
| Blocage / déblocage (v1.1) | 40 / h par compte |
| Suppression de compte (v1.1) | 5 / h par compte, 10 / h par IP, comptées avant la vérification du mot de passe |
| Recherche (v1.1) | 60 / 5 min par IP |

Compromis assumé : la limite par identifiant peut bloquer 15 minutes la connexion d'une personne visée par des essais répétés ; la réponse reste identique que le compte existe ou non.

## 4. Sécurité

- Jetons hachés, révocables, liés à un appareil ; 401 immédiat si le compte est désactivé.
- `$_COOKIE` est vidé et `session_id` ignoré dès le point d'entrée.
- Aucune donnée sensible renvoyée : pas d'e-mail, d'IP, de solde ni de jeton de rafraîchissement.
- Alertes : le suivi se fait **côté serveur** (`cl_mobile_alerts`), jamais par un cookie ; un identifiant de ticket deviné ne donne accès à rien.
- Erreurs internes journalisées (`error_log`), jamais affichées ; sortie tamponnée pour qu'aucun avertissement PHP ne casse le JSON.
- L'en-tête `Authorization` est transmis à PHP par `.htaccess` (`E=HTTP_AUTHORIZATION`) ; sur certains mutualisés, vérifier qu'il arrive bien (voir §6).

## 5. Tables (créées à la demande, aucune migration)

`cl_mobile_tokens`, `cl_mobile_devices`, `cl_mobile_alerts`, `cl_mobile_idem` (+ `cl_rate_limits`, déjà utilisée par « Mot de passe oublié »). La v1.1 n'ajoute **aucune** table : signalements, blocages et stories réutilisent `cl_pub_reports`, `cl_profile_reports`, `cl_blocks` et la colonne `swift` des utilisateurs.
Nettoyage automatique : idempotence après 24 h, jetons expirés depuis plus de 7 jours.

## 6. Mise en production

1. Envoyer : `mobile_v1.php`, `core/apps/mobile_v1/` (3 fichiers : `lib.php`, `routes.php`, `routes_v11.php`), `.htaccess` (règles `api/mobile/v1` et `E=HTTP_AUTHORIZATION`), et `nginx.conf` si Nginx.
2. `core/settings.php` de production avec `$ganho_api_url = https://ganho.app/api` et le **même secret** que `FORUM_ALERT_SECRET` de ganhovbg.
3. Tester depuis un terminal :
   ```bash
   curl -s https://forum.ganho.app/api/mobile/v1/status
   curl -s -H "Authorization: Bearer 0000000000000000000000000000000000000000000000000000000000000000" https://forum.ganho.app/api/mobile/v1/me   # 401 attendu
   ```
4. Après connexion réelle, vérifier que `GET me` répond 200. Si `Authorization` n'arrive pas à PHP (réponse 401 alors que le jeton est bon), l'hébergeur le supprime : ajouter `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` ou demander l'activation de l'en-tête.
5. Le certificat SSL de `forum.ganho.app` doit être actif (la redirection HTTPS est déjà dans `.htaccess`).

## 7. Non livré (volontairement)

Inscription, mot de passe oublié, envoi d'images et de vidéos (publier avec média), vote dans un sondage, publication de stories, messagerie privée, suivre / ne plus suivre un compte,
réactions autres que le « j'aime » (le web n'en a pas), et **l'envoi des notifications push** (le jeton est enregistré, rien n'est encore émis).
Livré en v1.1 (donc retiré de cette liste) : suppression de compte, signalements, blocage, profils publics, stories (lecture), recherche.
Chaque point restant demande une décision ou un test dédié ; ils sont à planifier avec l'application (voir `ganhovbg/docs/mobile/PLAN_MOBILE_APP.md`).

## 8. Tests faits (14 novembre 2026, bases locales, serveur `php -S`)

`status`, 404, 401 sans jeton ; enregistrement d'appareil ; connexion refusée puis réussie ; `me` ; fil (2 publications) ; publication visible / invisible ;
« j'aime » deux fois (bascule, compteur revenu à sa valeur) ; publication avec `Idempotency-Key` rejouée (même réponse, `Idempotent-Replay`, **une seule** ligne créée) ;
texte vide (422) ; notifications ; 21 fiches éducatives et détail ; 4 bandes dessinées avec 5 cases et bulles ; alerte : configuration, création sans compte,
liste, lecture des messages, **404 depuis un autre appareil**, réponse. Toutes les données de test ont été supprimées (comptages identiques à l'état initial).
**Non testé** : Apache réel et en-tête `Authorization` sur l'hébergeur, forte charge, mot interdit (`banned_word`), réponses à un fil (`thread_id`), `DELETE devices`, expiration réelle des jetons, pas de test automatisé PHP dans ce dépôt.

### Tests v1.1 (16 novembre 2026, bases locales, trois comptes jetables `mvtesta/b/c/d`, tous supprimés ensuite)

Signalement de publication et de profil : nominal (201), répété (200, une seule ligne), motif invalide, détails > 500, publication inconnue (404), sans jeton (401), propre contenu (403), méthode GET (405), **11e signalement en 1 h : 429 avec `Retry-After`** ; la ligne apparaît dans la file de modération de l'admin (`cl_adm_queue` : commentaire « [Harcèlement] … », 2 signalements en attente).
Blocage : bloquer, re-bloquer (idempotent), se bloquer soi-même (403), compte inconnu (404), liste, débloquer deux fois ; avec A bloquant B (ils se suivaient) : abonnements retirés, la publication de B disparaît du fil de A, ses réponses et sa notification « j'aime » sont exclues pour A, `users/B`, `users/B/posts`, `posts/B` répondent 404 pour A, A n'apparaît plus dans la recherche de B ni B dans celle de A, et B reçoit 404 sur les publications et le profil de A ; stories de B masquées pour A.
Profils : profil, onglets posts / media / likes avec pagination `before` (nouveau curseur du fil vérifié sur 2 pages), onglet inconnu (422), profil privé (`followers`) : 404 puis 200 une fois abonné ; story simulée (insérée en base puis retirée).
Suppression de compte : sans mot de passe et mot de passe faux (422), sans jeton (401), 6 essais d'affilée (429 au 6e), suppression réussie par `DELETE me` et par `POST me/delete` : jeton ensuite 401, connexion refusée, comptes de lignes **à zéro** pour le compte supprimé dans `cl_users`, `cl_publications`, `cl_mobile_tokens`, `cl_mobile_devices`, `cl_mobile_alerts` (suivi du compte et de son appareil), `cl_blocks`, `cl_pub_reports`, `cl_publikes`, `cl_connections` ; compteur de « j'aime » d'une publication tierce décrémenté.
Éducation / BD : `featured` et `featured_count` (5 fiches, 1 BD). Après nettoyage, les comptages de toutes les tables du forum sont identiques à l'état initial (comptes `usertest` / `admintest` intacts).
**Non testé** : refus d'un compte administrateur (volontairement, pour ne jamais risquer de supprimer `admintest`), vidéos (`poster_url`), publications `gif` / `audio`, Apache réel (en-tête `Authorization`, méthode DELETE), forte charge.

