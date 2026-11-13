<?php
/**
 * Panneau de contrôle : 8 pages mères, chacune avec ses onglets.
 * Chaque onglet pointe vers une page EXISTANTE (admin_panel/<section>), dont le contenu est inchangé.
 * 'match' = sections qui rendent l'onglet actif.
 */
function cl_cp_groups() {
    $all = cl_cp_groups_all();
    // Modérateur·rice (non administrateur) : uniquement la file des signalements.
    if (!empty($GLOBALS['cl']['is_moderator']) && empty($GLOBALS['cl']['is_admin'])) {
        foreach ($all as $g) {
            foreach ($g['tabs'] as $t) {
                if ($t[1] === 'moderation_queue') {
                    $g['tabs'] = array($t);
                    $g['title'] = 'Modération';
                    $g['desc'] = 'Traitez les signalements de la communauté.';
                    return array($g);
                }
            }
        }
    }
    return $all;
}

function cl_cp_groups_all() {
    return array(
        array('id' => 'dashboard', 'title' => 'Tableau de bord', 'icon' => 'chart', 'desc' => 'Vue d’ensemble de la communauté.', 'tabs' => array(
            array('Tableau de bord', 'dashboard', array('dashboard')),
        )),
        array('id' => 'settings', 'title' => 'Réglages', 'icon' => 'settings', 'desc' => 'Configuration générale du site, des fonctions, de l’e-mail et de la connexion.', 'tabs' => array(
            array('Réglages généraux', 'gen_settings', array('gen_settings')),
            array('Fonctions (activer / désactiver)', 'toggle_features', array('toggle_features'), 'Nouveau'),
            array('Paramètres SMTP', 'email_settings', array('email_settings')),
            array('Connexion oAuth', 'oauth_settings', array('oauth_settings')),
        )),
        array('id' => 'community', 'title' => 'Communauté', 'icon' => 'people', 'desc' => 'Les membres et leurs invitations.', 'tabs' => array(
            array('Utilisateurs', 'users', array('users', 'wallet_balance')),
            array('Inviter des utilisateurs', 'invite_users', array('invite_users'), 'Nouveau'),
        )),
        array('id' => 'campaigns', 'title' => 'Campagnes e-mail', 'icon' => 'envelope', 'desc' => 'Envoyez des informations à vos membres et gérez les modèles d’e-mail.', 'tabs' => array(
            array('Diffusions', 'campaigns', array('campaigns'), 'Nouveau'),
            array('Modèles d’e-mail', 'email_templates', array('email_templates'), 'Nouveau'),
        )),
        array('id' => 'modplus', 'title' => 'Modération renforcée', 'icon' => 'shield', 'desc' => 'File des signalements, mots interdits et journal d’audit des actions de modération.', 'tabs' => array(
            array('File des signalements', 'moderation_queue', array('moderation_queue'), 'Nouveau'),
            array('Mots interdits', 'banned_words', array('banned_words'), 'Nouveau'),
            array('Journal d’audit', 'audit_log', array('audit_log'), 'Nouveau'),
        )),
        array('id' => 'memberroles', 'title' => 'Membres et rôles', 'icon' => 'star', 'desc' => 'Annuaire, rôles, badges de reconnaissance et sanctions.', 'tabs' => array(
            array('Annuaire et rôles', 'members_roles', array('members_roles'), 'Nouveau'),
        )),
        array('id' => 'spaces', 'title' => 'Espaces et mise en avant', 'icon' => 'list-alt', 'desc' => 'Catégories de discussion et éléments épinglés (guides, ressources d’urgence).', 'tabs' => array(
            array('Catégories et espaces', 'forum_spaces', array('forum_spaces'), 'Nouveau'),
            array('Mise en avant', 'pinned_items', array('pinned_items'), 'Nouveau'),
        )),
        array('id' => 'engagement', 'title' => 'Engagement', 'icon' => 'calendar-event', 'desc' => 'Annonces globales, événements et suggestions des membres.', 'tabs' => array(
            array('Annonces globales', 'global_announcements', array('global_announcements'), 'Nouveau'),
            array('Événements', 'events_calendar', array('events_calendar'), 'Nouveau'),
            array('Boîte à suggestions', 'suggestions_box', array('suggestions_box'), 'Nouveau'),
        )),
        array('id' => 'education', 'title' => 'Contenus pédagogiques', 'icon' => 'book-opened', 'desc' => 'Fiches éducatives et bandes dessinées visibles par les membres.', 'tabs' => array(
            array('Contenus éducatifs', 'edu_content', array('edu_content'), 'Nouveau'),
            array('Bandes dessinées', 'edu_comics', array('edu_comics'), 'Nouveau'),
        )),
        array('id' => 'moderation', 'title' => 'Contenus et modération', 'icon' => 'danger', 'desc' => 'Publications, vérifications de compte et signalements.', 'tabs' => array(
            array('Swifts (publications)', 'publications', array('publications')),
            array('Tickets utilisateurs', 'account_verification', array('account_verification')),
            array('Signalements de comptes', 'account_reports', array('account_reports')),
            array('Signalements de publications', 'post_reports', array('post_reports')),
        )),
        array('id' => 'appearance', 'title' => 'Apparence', 'icon' => 'colours', 'desc' => 'Habillage, affichage et langues de l’interface.', 'tabs' => array(
            array('Thèmes', 'themes', array('themes')),
            array('Paramètres d’affichage', 'display_settings', array('display_settings')),
            array('Langues de l’interface', 'languages', array('languages'), 'Nouveau'),
        )),
        array('id' => 'broadcast', 'title' => 'Diffusion', 'icon' => 'bell', 'desc' => 'Notifications, publicités et applications mobiles.', 'tabs' => array(
            array('Notifications push', 'push_notifications', array('push_notifications')),
            array('Google Ads', 'google_ads', array('google_ads')),
            array('Liens des applications mobiles', 'app_links', array('app_links')),
        )),
        array('id' => 'security', 'title' => 'Sécurité et référencement', 'icon' => 'ok-circle', 'desc' => 'Protection anti-spam et plan du site.', 'tabs' => array(
            array('Google reCAPTCHA', 'recaptcha', array('recaptcha')),
            array('Sitemap', 'sitemap', array('sitemap')),
        )),
        array('id' => 'maintenance', 'title' => 'Maintenance', 'icon' => 'save', 'desc' => 'Sauvegardes, stockage et entretien de la base.', 'tabs' => array(
            array('Sauvegardes', 'backups', array('backups')),
            array('Stockage AWS S3', 'aws3', array('aws3')),
            array('Supprimer les swifts expirés', 'delete_swifts', array('delete_swifts')),
            array('Supprimer les comptes de spam', 'delete_spam_accounts', array('delete_spam_accounts')),
            array('Journal des modifications', 'changelogs', array('changelogs')),
        )),
    );
}

/** Retourne la page mère contenant la section courante (ou null). */
function cl_cp_active_group($section) {
    foreach (cl_cp_groups() as $g) {
        foreach ($g['tabs'] as $t) {
            if (in_array($section, $t[2], true)) {
                return $g;
            }
        }
    }
    return null;
}
