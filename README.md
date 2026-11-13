<p align="center"><img src="docs/social-preview.png" alt="Forum GANXÔ : un espace sûr pour échanger, contenus éducatifs, bandes dessinées et alerte VBG en chat" width="100%"></p>

# GANXÔ · Forum

**Forum communautaire d'entraide pour la communauté LGBTQ au Bénin** et les personnes concernées par les violences basées sur le genre (VBG). Fil de discussion sous pseudonyme, contenus éducatifs, bandes dessinées interactives et alerte VBG en chat, dans un espace modéré.

En ligne : **[forum.ganho.app](https://forum.ganho.app)** · Un projet de l'association Bénin Synergies PLUS · BESYP (Abomey-Calavi, Bénin).

L'application d'alerte associée est dans le dépôt [ganhovbg](https://github.com/massenon/ganhovbg).

---

## Le projet

Le forum est construit sur le script ColibriSM (PHP procédural, sans framework ni Composer), repris et habillé au design system Ganho : interface en français, accessibilité WCAG AA, mode nuit, installation sur mobile (PWA) et panneau d'administration réorganisé.

La règle de conception : **un espace sûr, où l'on peut parler sans se mettre en danger**.

## Fonctionnalités

### Pour les membres
| | |
|---|---|
| **Connexion et inscription** | Page unique à onglets, « Rester connecté·e », mot de passe oublié avec jeton à usage unique valable 1 h, sans énumération de comptes |
| **Fil et messages** | Publications, commentaires, abonnements, messages privés, mode clair et nuit |
| **Contenus éducatifs** | 21 fiches structurées (droits, santé, sécurité numérique) avec recherche et filtres |
| **Bandes dessinées** | BD en cinq cases avec **bulles de conversation** (« dit », « pense », « ressent »), révélation pas à pas, légende des couleurs, texte alternatif généré |
| **Alerte VBG en chat** | Création d'une alerte depuis le forum, transmise de façon signée à l'équipe de [ganhovbg](https://github.com/massenon/ganhovbg), anonyme possible |
| **Événements et suggestions** | Calendrier public, boîte à suggestions |
| **PWA** | Installable sur mobile, page de repli hors ligne |

### Pour l'équipe
| | |
|---|---|
| **Panneau de contrôle** | Huit pages mères à onglets, tableau de bord avec indicateurs, mode nuit |
| **Modération** | File des signalements, mots interdits, journal d'audit, sanctions, avertissements visibles par le membre, rôle modérateur à accès limité |
| **Membres et rôles** | Annuaire, rôles, badges |
| **Campagnes e-mail** | Galerie de modèles, éditeur, aperçu, test, envoi par lots, désinscription en un clic |
| **Contenus pédagogiques** | Création, duplication, publication, épinglage, éditeur de bulles par glisser-déposer |

## Sécurité et vie privée

- Mots de passe hachés (`password_hash`), HTTPS forcé, cookies sans pistage publicitaire.
- Limiteur de débit sur « Mot de passe oublié » (6 demandes par 15 min et par IP, 3 par heure et par adresse).
- `.htaccess` qui interdit l'accès à `install/`, `core/seeders`, `core/cron`, `core/logs`, `site_backups`, aux journaux et aux dumps.
- Pages d'erreur statiques (`erreur.html`) et vrais codes HTTP sur `/404` et `/500`.
- Textes légaux (conditions, confidentialité, cookies) rédigés pour le service.
- Liaison avec l'alerte VBG signée par HMAC-SHA256.

## Technologies

| Couche | Choix |
|---|---|
| Back-end | PHP 8, MySQL (MysqliDb), pas de framework |
| Front | Modèles `.phtml`, JavaScript sans framework (Vue pour le composeur), CSS Ganho |
| Serveur | Apache (`.htaccess`) ou Nginx (`nginx.conf`) |
| Hébergement | cPanel mutualisé |

## Installation en local

```bash
git clone https://github.com/massenon/ganhoforum.git
cd ganhoforum
cp core/settings.example.php core/settings.php
# renseigner la base MySQL, $site_url, $ganho_api_url et $ganho_api_secret
```

1. Créer une base MySQL (utf8mb4) et l'importer via l'installateur (`/install`) ou depuis une sauvegarde.
2. Pointer Apache ou Nginx sur le dossier, ou lancer un serveur de développement : `php -S 127.0.0.1:8001`.
3. Charger le contenu de départ : `GANHO_ALLOW_SEED=1 php core/seeders/educational_content.php`.

`core/settings.php` est ignoré par git. Le secret `$ganho_api_secret` doit être identique à `FORUM_ALERT_SECRET` dans le `.env` de ganhovbg (`php -r "echo bin2hex(random_bytes(32));"`).

Les comptes de démonstration (`php core/seeders/test_accounts.php`) refusent de s'exécuter hors d'un environnement local.

## Tâche planifiée

Pour les campagnes e-mail programmées, une tâche cron chaque minute :

```
* * * * * php /chemin/vers/forumganho/core/cron/send_campaigns.php
```

## Structure du dépôt

```
forumganho/
├── apps/             Contrôleurs HTTP et AJAX par application
├── core/             Configuration, composants, langues, bibliothèques, seeders, cron
├── themes/default/   Vues .phtml et ressources du design Ganho (CSS, JS, images, BD)
├── install/          Installateur
├── upload/           Médias des membres (non publiés, seul upload/default est suivi)
├── .htaccess         Réécriture d'URL, sécurité, pages d'erreur
└── nginx.conf        Équivalent Nginx
```

## Licence

Voir le fichier [LICENSE](LICENSE). Le script d'origine ColibriSM reste soumis à sa propre licence.
