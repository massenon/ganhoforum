<?php
# API mobile v1.1 : signalement, blocage, suppression de compte, profils publics, publications d'un profil, stories, recherche.
# Réutilise le web : tables cl_pub_reports / cl_profile_reports (file « Modération » de l'admin), cl_blocks, cl_delete_user_data (/settings/delete),
# cl_get_profile_posts / cl_get_profile_likes / cl_can_view_profile, cl_timeline_swifts, cl_search_people / cl_search_posts.

function mv1_routes_v11() {
    return array(
        array('POST',   '#^posts/(\d{1,12})/report$#',       'mv1_h_post_report',    true),
        array('POST',   '#^users/(\d{1,12})/report$#',       'mv1_h_user_report',    true),
        array('POST',   '#^users/(\d{1,12})/block$#',        'mv1_h_user_block',     true),
        array('DELETE', '#^users/(\d{1,12})/block$#',        'mv1_h_user_unblock',   true),
        array('GET',    '#^me/blocks$#',                     'mv1_h_me_blocks',      true),
        array('DELETE', '#^me$#',                            'mv1_h_me_delete',      true),
        array('POST',   '#^me/delete$#',                     'mv1_h_me_delete',      true),
        array('GET',    '#^users/(\d{1,12})$#',              'mv1_h_user_get',       true),
        array('GET',    '#^users/(\d{1,12})/posts$#',        'mv1_h_user_posts',     true),
        array('GET',    '#^stories$#',                       'mv1_h_stories',        true),
        array('GET',    '#^search$#',                        'mv1_h_search',         true),
    );
}

/* ------------------------------------------------------------------ Signalement */

# Motifs de l'application -> [libellé français, identifiant de motif du web pour les publications, pour les profils].
# Le web ne stocke qu'un identifiant numérique (cl_pub_reports.reason / cl_profile_reports.reason) : le libellé exact est préfixé au commentaire,
# seul champ affiché dans la file « Modération » de l'admin.
function mv1_report_reasons() {
    return array(
        'harassment'    => array('Harcèlement',      '4', '3'),
        'hate'          => array('Haine',            '4', '3'),
        'violence'      => array('Violence',         '4', '3'),
        'sexual'        => array('Contenu sexuel',   '6', '3'),
        'spam'          => array('Spam',             '1', '1'),
        'impersonation' => array('Usurpation',       '2', '2'),
        'other'         => array('Autre',            '6', '6'),
    );
}

function mv1_report_input() {
    $in = mv1_input();
    $reason = mv1_str($in, 'reason', 20);
    $details = mv1_str($in, 'details', 600);
    $reasons = mv1_report_reasons();
    $errors = array();
    if (!isset($reasons[$reason])) { $errors['reason'] = array("Choisissez un motif : " . implode(', ', array_keys($reasons)) . "."); }
    if (mb_strlen($details) > 500) { $errors['details'] = array("500 caractères maximum."); }
    if ($errors) { mv1_error(422, 'validation_failed', "Vérifiez les champs signalés.", $errors); }
    return array($reason, $reasons[$reason], $details);
}

function mv1_h_post_report($id) {
    global $db, $me, $mv1;
    list($reason, $map, $details) = mv1_report_input();
    mv1_limit('report:' . $mv1['user_id'], 10, 3600);
    mv1_limit('report-ip:' . mv1_ip(), 40, 3600);
    $raw = cl_raw_post_data((int) $id);
    if (empty($raw)) { mv1_error(404, 'not_found', "Publication introuvable."); }
    $pd = cl_post_data($raw);
    if (empty($pd['can_see'])) { mv1_error(404, 'not_found', "Publication introuvable."); }
    if ((int) $raw['user_id'] === (int) $me['id']) { mv1_error(403, 'cannot_target_self', "Vous ne pouvez pas signaler votre propre publication."); }
    if (cl_is_reported($me['id'], (int) $id)) {
        mv1_ok(array('reported' => true, 'already_reported' => true));
    }
    cl_db_insert(T_PUB_REPORTS, array(
        'user_id' => $me['id'], 'post_id' => (int) $id, 'reason' => $map[1],
        'comment' => cl_croptxt('[' . $map[0] . ($details !== '' ? '] ' . $details : ']'), 2900),
        'seen' => '0', 'time' => time(),
    ));
    mv1_ok(array('reported' => true, 'already_reported' => false), null, 201);
}

function mv1_h_user_report($id) {
    global $db, $me, $mv1;
    list($reason, $map, $details) = mv1_report_input();
    mv1_limit('report:' . $mv1['user_id'], 10, 3600);
    mv1_limit('report-ip:' . mv1_ip(), 40, 3600);
    $u = cl_raw_user_data((int) $id);
    if (empty($u) || (string) $u['active'] !== '1') { mv1_error(404, 'not_found', "Compte introuvable."); }
    if ((int) $id === (int) $me['id']) { mv1_error(403, 'cannot_target_self', "Vous ne pouvez pas vous signaler vous-même."); }
    $ex = $db->rawQueryOne("SELECT id FROM `" . T_PROF_REPORTS . "` WHERE user_id = ? AND profile_id = ?", array((int) $me['id'], (int) $id));
    if ($ex) { mv1_ok(array('reported' => true, 'already_reported' => true)); }
    cl_db_insert(T_PROF_REPORTS, array(
        'user_id' => $me['id'], 'profile_id' => (int) $id, 'reason' => $map[2],
        'comment' => cl_croptxt('[' . $map[0] . ($details !== '' ? '] ' . $details : ']'), 2900),
        'seen' => '0', 'time' => time(),
    ));
    mv1_ok(array('reported' => true, 'already_reported' => false), null, 201);
}

/* ------------------------------------------------------------------ Blocage */

function mv1_h_user_block($id) {
    global $me, $mv1;
    mv1_limit('block:' . $mv1['user_id'], 40, 3600);
    $u = cl_raw_user_data((int) $id);
    if (empty($u) || (string) $u['active'] !== '1') { mv1_error(404, 'not_found', "Compte introuvable."); }
    if ((int) $id === (int) $me['id']) { mv1_error(403, 'cannot_target_self', "Vous ne pouvez pas vous bloquer vous-même."); }
    if (!cl_is_blocked($me['id'], (int) $id)) {
        # Même logique que apps/native/api/block_user : insertion puis désabonnement dans les deux sens.
        cl_db_insert(T_BLOCKS, array('user_id' => $me['id'], 'profile_id' => (int) $id, 'time' => time()));
        if (cl_is_following($me['id'], (int) $id)) { cl_unfollow($me['id'], (int) $id); cl_follow_decrease($me['id'], (int) $id); }
        if (cl_is_following((int) $id, $me['id'])) { cl_unfollow((int) $id, $me['id']); cl_follow_decrease((int) $id, $me['id']); }
    }
    mv1_ok(array('blocked' => true));
}

function mv1_h_user_unblock($id) {
    global $me, $mv1;
    mv1_limit('block:' . $mv1['user_id'], 40, 3600);
    if (cl_is_blocked($me['id'], (int) $id)) {
        cl_db_delete_item(T_BLOCKS, array('user_id' => $me['id'], 'profile_id' => (int) $id));
    }
    mv1_ok(array('blocked' => false));
}

function mv1_h_me_blocks() {
    global $db, $me;
    list($limit, $before) = mv1_page_args(30, 100);
    $sql = "SELECT b.id AS bid, b.time AS btime, u.id, u.username, u.fname, u.lname, u.avatar, u.verified FROM `" . T_BLOCKS . "` b INNER JOIN `" . T_USERS . "` u ON u.id = b.profile_id WHERE b.user_id = ?";
    $args = array((int) $me['id']);
    if ($before) { $sql .= " AND b.id < ?"; $args[] = $before; }
    $sql .= " ORDER BY b.id DESC LIMIT " . (int) $limit;
    $rows = $db->rawQuery($sql, $args);
    $out = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $o = mv1_user_out(array('id' => $r['id'], 'username' => $r['username'], 'name' => trim($r['fname'] . ' ' . $r['lname']), 'avatar' => cl_get_media($r['avatar']), 'verified' => $r['verified']));
        $o['blocked_at'] = (int) $r['btime'];
        $out[] = $o;
    }
    $last = $rows ? (int) $rows[count($rows) - 1]['bid'] : null;
    mv1_ok($out, array('limit' => $limit, 'has_more' => count($rows) >= $limit, 'next_before' => count($rows) >= $limit ? $last : null));
}

/* ------------------------------------------------------------------ Suppression de compte */

function mv1_h_me_delete() {
    global $db, $me, $mv1;
    $uid = (int) $me['id'];
    # Limite stricte, AVANT la vérification du mot de passe (pas de force brute via cette route).
    mv1_limit('delacct:' . $uid, 5, 3600);
    mv1_limit('delacct-ip:' . mv1_ip(), 10, 3600);
    $in  = mv1_input();
    $pwd = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
    if ($pwd === '' || strlen($pwd) > 200 || !password_verify($pwd, (string) $me['password'])) {
        mv1_error(422, 'validation_failed', "Mot de passe incorrect.", array('password' => array("Mot de passe incorrect.")));
    }
    if ((string) $me['admin'] === '1') {
        mv1_error(403, 'admin_account', "Un compte administrateur se supprime depuis le panneau d'administration du site web.");
    }
    # Même routine que « Paramètres > Supprimer mon compte » du web (/settings/delete) : publications, réponses, mentions « j'aime », abonnements,
    # signalements, blocages, notifications, messages, signets, sessions web, puis le compte.
    if (!cl_delete_user_data($uid)) { mv1_error(500, 'server_error', "La suppression a échoué. Réessayez dans un instant."); }
    # Données propres à l'application mobile.
    $db->rawQuery("DELETE FROM `cl_mobile_tokens` WHERE user_id = ?", array($uid));
    # Alertes suivies depuis ce compte OU depuis ses appareils (le suivi côté forum disparaît ; les alertes déjà remises à ganhovbg restent côté association).
    foreach ((array) $db->rawQuery("SELECT device_id FROM `cl_mobile_devices` WHERE user_id = ?", array($uid)) as $d) {
        $db->rawQuery("DELETE FROM `cl_mobile_alerts` WHERE owner = ?", array('d' . $d['device_id']));
    }
    $db->rawQuery("DELETE FROM `cl_mobile_devices` WHERE user_id = ?", array($uid));
    $db->rawQuery("DELETE FROM `cl_mobile_alerts` WHERE owner = ?", array('u' . $uid));
    error_log('[mobile_v1] account_deleted');
    mv1_ok(array('deleted' => true));
}

/* ------------------------------------------------------------------ Profils publics */

/** Retourne [ligne utilisateur (cl_user_data), raccourci can_view] ou répond 404 (inconnu, inactif, bloqué dans un sens ou l'autre, profil privé). */
function mv1_visible_profile($id) {
    global $me;
    $id = (int) $id;
    $u = cl_user_data($id);
    if (empty($u) || (string) $u['active'] !== '1') { mv1_error(404, 'not_found', "Compte introuvable."); }
    if ($id !== (int) $me['id']) {
        if (mv1_is_blocked_either($id)) { mv1_error(404, 'not_found', "Compte introuvable."); }
        require_once(cl_full_path("core/apps/profile/app_ctrl.php"));
        if (!cl_can_view_profile($id)) { mv1_error(404, 'not_found', "Compte introuvable."); }
    }
    return $u;
}

function mv1_h_user_get($id) {
    global $db, $me;
    $u = mv1_visible_profile($id);
    $o = mv1_user_out($u);
    $o['about'] = mv1_plain(isset($u['about']) ? $u['about'] : '');
    $o['cover'] = isset($u['cover']) ? (string) $u['cover'] : '';
    $o['counts'] = array('posts' => (int) $u['posts'], 'followers' => (int) $u['followers'], 'following' => (int) $u['following']);
    $j = $db->rawQueryOne("SELECT joined FROM `" . T_USERS . "` WHERE id = ?", array((int) $id));
    $o['joined_at'] = $j && is_numeric($j['joined']) ? (int) $j['joined'] : null;
    $o['is_me'] = ((int) $id === (int) $me['id']);
    $o['is_following'] = false;
    $o['follows_me'] = false;
    if (!$o['is_me']) {
        $a = $db->rawQueryOne("SELECT 1 AS x FROM `" . T_CONNECTIONS . "` WHERE follower_id = ? AND following_id = ? AND status = 'active'", array((int) $me['id'], (int) $id));
        $b = $db->rawQueryOne("SELECT 1 AS x FROM `" . T_CONNECTIONS . "` WHERE follower_id = ? AND following_id = ? AND status = 'active'", array((int) $id, (int) $me['id']));
        $o['is_following'] = (bool) $a;
        $o['follows_me'] = (bool) $b;
    }
    $o['tabs'] = array('posts', 'media', 'likes');
    mv1_ok($o);
}

function mv1_h_user_posts($id) {
    global $me;
    mv1_visible_profile($id);
    require_once(cl_full_path("core/apps/profile/app_ctrl.php"));
    list($limit, $before) = mv1_page_args(20, 50);
    $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'posts';
    if (!in_array($tab, array('posts', 'media', 'likes'), true)) { mv1_error(422, 'validation_failed', "Onglet inconnu.", array('tab' => array("Valeurs : posts, media, likes."))); }
    $list = ($tab === 'likes') ? cl_get_profile_likes((int) $id, $limit, $before ? $before : false) : cl_get_profile_posts((int) $id, $limit, $tab === 'media', $before ? $before : false);
    $list = is_array($list) ? $list : array();
    $cursor = null;
    foreach ($list as $p) { if (!empty($p['offset_id'])) { $cursor = (int) $p['offset_id']; } }
    $out = array();
    foreach (mv1_filter_posts($list) as $p) { $out[] = mv1_post_out($p); }
    $more = count($list) >= $limit;
    mv1_ok($out, array('tab' => $tab, 'limit' => $limit, 'has_more' => $more, 'next_before' => $more ? $cursor : null));
}

/* ------------------------------------------------------------------ Stories (« swifts » du web) */

function mv1_h_stories() {
    global $cl;
    if (($cl['config']['swift_system_status'] ?? 'off') !== 'on') {
        mv1_ok(array(), array('enabled' => false));
    }
    require_once(cl_full_path("core/apps/home/app_ctrl.php"));
    $rows = cl_timeline_swifts();
    $out = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        if (mv1_is_blocked_either($r['id'])) { continue; }
        $items = array();
        foreach ($r['swift'] as $s) {
            $media = null;
            if (isset($s['type']) && $s['type'] === 'image' && !empty($s['media']['src'])) { $media = (string) $s['media']['src']; }
            if (isset($s['type']) && $s['type'] === 'video' && !empty($s['media']['source'])) { $media = (string) $s['media']['source']; }
            $items[] = array(
                'id' => (string) $s['swid'], 'type' => (string) $s['type'], 'media_url' => $media,
                'text' => mv1_plain(isset($s['text']) ? $s['text'] : ''),
                'expires_at' => isset($s['exp_time']) ? (int) $s['exp_time'] : null,
                'seen' => !empty($s['seen']),
            );
        }
        $out[] = array(
            'user' => mv1_user_out(array('id' => $r['id'], 'username' => $r['username'], 'name' => $r['name'], 'avatar' => $r['avatar'])),
            'is_me' => !empty($r['is_user']), 'has_unseen' => !empty($r['has_unseen']), 'items' => $items,
        );
    }
    mv1_ok($out, array('enabled' => true, 'total' => count($out)));
}

/* ------------------------------------------------------------------ Recherche */

function mv1_h_search() {
    $q = mv1_str($_GET, 'q', 80);
    $type = isset($_GET['type']) ? (string) $_GET['type'] : 'posts';
    if (mb_strlen($q) < 2) { mv1_error(422, 'validation_failed', "Saisissez au moins 2 caractères.", array('q' => array("Au moins 2 caractères."))); }
    if (!in_array($type, array('posts', 'people'), true)) { mv1_error(422, 'validation_failed', "Type inconnu.", array('type' => array("Valeurs : posts, people."))); }
    mv1_limit('search:' . mv1_ip(), 60, 300);
    require_once(cl_full_path("core/apps/search/app_ctrl.php"));
    list($limit, $before) = mv1_page_args(15, 30);
    $keyword = cl_text_secure($q);
    $out = array();
    if ($type === 'people') {
        $list = cl_search_people($keyword, $before ? $before : false, $limit);
        $list = is_array($list) ? $list : array();
        foreach ($list as $r) {
            if (mv1_is_blocked_either($r['id'])) { continue; }
            $o = mv1_user_out($r);
            $o['about'] = mv1_plain(isset($r['about']) ? $r['about'] : '');
            $out[] = $o;
        }
        $last = $list ? (int) $list[count($list) - 1]['id'] : null;
    } else {
        $list = cl_search_posts($keyword, $before ? $before : false, $limit);
        $list = is_array($list) ? $list : array();
        foreach (mv1_filter_posts($list) as $p) { $out[] = mv1_post_out($p); }
        $last = $list ? (int) $list[count($list) - 1]['id'] : null;
    }
    $more = count($list) >= $limit;
    mv1_ok($out, array('type' => $type, 'limit' => $limit, 'has_more' => $more, 'next_before' => $more ? $last : null));
}
