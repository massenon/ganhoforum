<?php
# API mobile v1 : routes et gestionnaires.
# Chaque route : [méthode, motif, gestionnaire, authentification requise]. Les gestionnaires reçoivent les captures du motif.

function mv1_routes() {
    return array_merge(array(
        array('GET',    '#^status$#',                        'mv1_h_status',          false),
        array('POST',   '#^devices$#',                       'mv1_h_device_register', false),
        array('DELETE', '#^devices/([A-Fa-f0-9-]{16,64})$#', 'mv1_h_device_delete',   true),
        array('POST',   '#^auth/login$#',                    'mv1_h_login',           false),
        array('POST',   '#^auth/logout$#',                   'mv1_h_logout',          true),
        array('GET',    '#^me$#',                            'mv1_h_me',              true),
        array('POST',   '#^me/push-token$#',                 'mv1_h_push_token',      true),
        array('GET',    '#^feed$#',                          'mv1_h_feed',            true),
        array('POST',   '#^posts$#',                         'mv1_h_post_create',     true),
        array('GET',    '#^posts/(\d{1,12})$#',              'mv1_h_post_get',        true),
        array('GET',    '#^posts/(\d{1,12})/replies$#',      'mv1_h_post_replies',    true),
        array('POST',   '#^posts/(\d{1,12})/like$#',         'mv1_h_post_like',       true),
        array('GET',    '#^notifications$#',                 'mv1_h_notifications',   true),
        array('GET',    '#^education$#',                     'mv1_h_edu_list',        false),
        array('GET',    '#^education/([a-z0-9-]{1,120})$#',  'mv1_h_edu_get',         false),
        array('GET',    '#^comics$#',                        'mv1_h_comics_list',     false),
        array('GET',    '#^comics/([a-z0-9-]{1,120})$#',     'mv1_h_comics_get',      false),
        array('GET',    '#^alerts/config$#',                 'mv1_h_alert_config',    false),
        array('GET',    '#^alerts$#',                        'mv1_h_alert_list',      false),
        array('POST',   '#^alerts$#',                        'mv1_h_alert_create',    false),
        array('GET',    '#^alerts/([A-Za-z0-9-]{3,30})/messages$#',  'mv1_h_alert_messages', false),
        array('POST',   '#^alerts/([A-Za-z0-9-]{3,30})/messages$#',  'mv1_h_alert_reply',    false),
    ), mv1_routes_v11());
}

function mv1_dispatch() {
    global $mv1;
    $path = isset($_GET['route']) ? trim((string) $_GET['route'], '/') : '';
    if ($path === '$1') { $path = ''; }
    $method = mv1_method();
    if ($method === 'OPTIONS') { mv1_send(204, array()); }

    # Limite générale par acteur (jeton, appareil ou IP) : 300 requêtes / 5 min.
    $actor = $mv1['user_id'] ? 'u' . $mv1['user_id'] : ($mv1['device_id'] !== '' ? 'd' . $mv1['device_id'] : 'i' . mv1_ip());
    mv1_limit('all:' . $actor, 300, 300);

    $path_known = false;
    foreach (mv1_routes() as $r) {
        if (!preg_match($r[1], $path, $m)) { continue; }
        $path_known = true;
        if ($r[0] !== $method) { continue; }
        if ($r[3]) { mv1_require_auth(); }
        array_shift($m);
        call_user_func_array($r[2], $m);
        mv1_error(500, 'server_error', "Réponse manquante.");
    }
    if ($path_known) { mv1_error(405, 'method_not_allowed', "Méthode non autorisée pour cette route."); }
    mv1_error(404, 'not_found', "Ressource introuvable.");
}

/* ------------------------------------------------------------------ Santé et appareils */

function mv1_h_status() {
    global $config;
    mv1_ok(array(
        'ok'          => true,
        'service'     => 'forum-ganxo',
        'api_version' => 1,
        'maintenance' => false,
        'min_app_version' => '1.0.0',
        'time'        => time(),
    ));
}

function mv1_h_device_register() {
    global $db, $mv1;
    $in = mv1_input();
    $id = mv1_str($in, 'device_id', 64);
    if (!mv1_uuid_ok($id)) { mv1_error(422, 'validation_failed', "Identifiant d'appareil invalide.", array('device_id' => array("Identifiant d'appareil invalide."))); }
    $platform = mv1_str($in, 'platform', 12);
    if (!in_array($platform, array('android', 'ios'), true)) { mv1_error(422, 'validation_failed', "Plateforme invalide.", array('platform' => array("Valeurs : android, ios."))); }
    mv1_limit('devreg:' . mv1_ip(), 30, 3600);
    $row = $db->rawQueryOne("SELECT revoked, user_id FROM `cl_mobile_devices` WHERE device_id = ?", array($id));
    if ($row && (int) $row['revoked'] === 1) { mv1_error(403, 'device_revoked', "Cet appareil a été révoqué."); }
    $ver = preg_replace('/[^0-9A-Za-z.+_-]/', '', mv1_str($in, 'app_version', 24));
    if ($row) {
        $db->rawQuery("UPDATE `cl_mobile_devices` SET platform = ?, app_version = ?, last_seen = ? WHERE device_id = ?", array($platform, $ver, time(), $id));
    } else {
        $db->rawQuery("INSERT INTO `cl_mobile_devices` (device_id, user_id, platform, app_version, created_at, last_seen) VALUES (?,?,?,?,?,?)", array($id, (int) $mv1['user_id'], $platform, $ver, time(), time()));
    }
    mv1_ok(array('device_id' => $id, 'registered' => true), null, $row ? 200 : 201);
}

function mv1_h_device_delete($id) {
    global $db, $mv1;
    $row = $db->rawQueryOne("SELECT user_id FROM `cl_mobile_devices` WHERE device_id = ?", array($id));
    if (!$row || (int) $row['user_id'] !== (int) $mv1['user_id']) { mv1_error(404, 'not_found', "Appareil introuvable."); }
    $db->rawQuery("UPDATE `cl_mobile_devices` SET revoked = 1, push_token = '' WHERE device_id = ?", array($id));
    $db->rawQuery("UPDATE `cl_mobile_tokens` SET revoked = 1 WHERE device_id = ? AND user_id = ?", array($id, (int) $mv1['user_id']));
    mv1_ok(array('revoked' => true));
}

/* ------------------------------------------------------------------ Compte */

function mv1_h_login() {
    global $db, $mv1;
    $in  = mv1_input();
    $idf = mv1_str($in, 'identifier', 120);
    $pwd = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
    $dev = mv1_str($in, 'device_id', 64);
    $platform = mv1_str($in, 'platform', 12);
    $errors = array();
    if ($idf === '') { $errors['identifier'] = array("Saisissez votre e-mail ou votre pseudo."); }
    if ($pwd === '' || strlen($pwd) > 200) { $errors['password'] = array("Saisissez votre mot de passe."); }
    if (!mv1_uuid_ok($dev)) { $errors['device_id'] = array("Identifiant d'appareil invalide."); }
    if (!in_array($platform, array('android', 'ios'), true)) { $errors['platform'] = array("Valeurs : android, ios."); }
    if ($errors) { mv1_error(422, 'validation_failed', "Vérifiez les champs signalés.", $errors); }

    # Limites : par IP et par identifiant (réponse identique que le compte existe ou non).
    mv1_limit('login-ip:' . mv1_ip(), 30, 900);
    mv1_limit('login-id:' . mb_strtolower($idf), 8, 900);

    $drow = $db->rawQueryOne("SELECT revoked FROM `cl_mobile_devices` WHERE device_id = ?", array($dev));
    if ($drow && (int) $drow['revoked'] === 1) { mv1_error(403, 'device_revoked', "Cet appareil a été révoqué."); }

    $row = $db->rawQueryOne("SELECT id, password, active FROM `" . T_USERS . "` WHERE (email = ? OR username = ?) AND active IN ('1','2') LIMIT 1", array($idf, $idf));
    $ok  = $row && password_verify($pwd, $row['password']);
    if (!$ok) { mv1_error(401, 'invalid_credentials', "Identifiants incorrects."); }
    if ((string) $row['active'] !== '1') { mv1_error(403, 'account_inactive', "Ce compte est désactivé ou en attente d'activation."); }

    if (!$drow) {
        $db->rawQuery("INSERT INTO `cl_mobile_devices` (device_id, user_id, platform, app_version, created_at, last_seen) VALUES (?,?,?,?,?,?)", array($dev, (int) $row['id'], $platform, '', time(), time()));
    } else {
        $db->rawQuery("UPDATE `cl_mobile_devices` SET user_id = ?, last_seen = ? WHERE device_id = ?", array((int) $row['id'], time(), $dev));
    }
    list($token, $exp) = mv1_new_token($row['id'], $dev, $platform);
    cl_update_user_data($row['id'], array('last_active' => time(), 'ip_address' => mv1_ip()));
    $u = cl_user_data($row['id']);
    mv1_ok(array('token' => $token, 'expires_at' => $exp, 'user' => mv1_me_out($u)), null, 200);
}

function mv1_me_out($u) {
    $o = mv1_user_out($u);
    $o['about'] = mv1_plain(isset($u['about']) ? $u['about'] : '');
    $o['spaces'] = array('member');
    if ((string) $u['admin'] === '1') { $o['spaces'][] = 'admin'; }
    $o['counts'] = array('posts' => (int) $u['posts'], 'followers' => (int) $u['followers'], 'following' => (int) $u['following']);
    return $o;
}

function mv1_h_logout() {
    global $db, $mv1;
    $db->rawQuery("UPDATE `cl_mobile_tokens` SET revoked = 1 WHERE id = ?", array((int) $mv1['token_id']));
    mv1_ok(array('logged_out' => true));
}

function mv1_h_me() {
    global $me;
    mv1_ok(mv1_me_out($me));
}

function mv1_h_push_token() {
    global $db, $mv1;
    $in = mv1_input();
    $tok = mv1_str($in, 'token', 255);
    if (strlen($tok) < 50 || !preg_match('/^[A-Za-z0-9:_\-.]+$/', $tok)) { mv1_error(422, 'validation_failed', "Jeton de notification invalide.", array('token' => array("Jeton invalide."))); }
    if ($mv1['device_id'] === '') { mv1_error(403, 'device_required', "Enregistrez d'abord l'appareil (X-Device-Id)."); }
    $db->rawQuery("UPDATE `cl_mobile_devices` SET push_token = ?, user_id = ?, last_seen = ? WHERE device_id = ? AND revoked = 0", array($tok, (int) $mv1['user_id'], time(), $mv1['device_id']));
    mv1_ok(array('saved' => true));
}

/* ------------------------------------------------------------------ Fil et publications */

function mv1_page_args($default = 20, $max = 50) {
    $limit  = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : $default;
    $limit  = max(1, min($max, $limit));
    $offset = isset($_GET['before']) && ctype_digit((string) $_GET['before']) ? (int) $_GET['before'] : null;
    return array($limit, $offset);
}

function mv1_h_feed() {
    require_once(cl_full_path("core/apps/home/app_ctrl.php"));
    list($limit, $before) = mv1_page_args();
    $posts = cl_get_timeline_feed($limit, $before);
    $posts = is_array($posts) ? $posts : array();
    # Curseur = offset_id (identifiant de la ligne du fil, pas celui de la publication) ; calculé AVANT le filtrage des blocages.
    $cursor = null;
    foreach ($posts as $p) { if (!empty($p['offset_id'])) { $cursor = (int) $p['offset_id']; } }
    $out = array();
    foreach (mv1_filter_posts($posts) as $p) { $out[] = mv1_post_out($p); }
    $more = count($posts) >= $limit;
    mv1_ok($out, array('limit' => $limit, 'next_before' => $more ? $cursor : null, 'has_more' => $more));
}

function mv1_h_post_get($id) {
    require_once(cl_full_path("core/apps/thread/app_ctrl.php"));
    $t = cl_get_thread_data((int) $id);
    if (empty($t['post']) || empty($t['post']['can_see']) || mv1_is_blocked_either($t['post']['user_id'])) { mv1_error(404, 'not_found', "Publication introuvable."); }
    mv1_ok(mv1_post_out($t['post']));
}

function mv1_h_post_replies($id) {
    require_once(cl_full_path("core/apps/thread/app_ctrl.php"));
    $t = cl_get_thread_data((int) $id);
    if (empty($t['post']) || empty($t['post']['can_see']) || mv1_is_blocked_either($t['post']['user_id'])) { mv1_error(404, 'not_found', "Publication introuvable."); }
    list($limit, $before) = mv1_page_args(15, 50);
    $list = cl_get_thread_child_posts((int) $id, $limit, ($before === null ? 0 : $before), 'lt');
    $list = is_array($list) ? $list : array();
    $out = array();
    foreach (mv1_filter_posts($list) as $p) { $out[] = mv1_post_out($p); }
    $last = $list ? (int) $list[count($list) - 1]['id'] : null;
    mv1_ok($out, array('limit' => $limit, 'has_more' => count($list) >= $limit, 'next_before' => count($list) >= $limit ? $last : null));
}

function mv1_h_post_create() {
    global $me, $cl, $db, $mv1;
    $key  = mv1_idem_begin('posts');
    $in   = mv1_input();
    $text = mv1_str($in, 'text', 20000);
    $max  = (int) $cl['config']['max_post_len'];
    $max  = $max > 0 ? $max : 1000;
    $thread = isset($in['thread_id']) && is_numeric($in['thread_id']) ? (int) $in['thread_id'] : 0;
    $privacy = mv1_str($in, 'privacy', 12);
    if (!in_array($privacy, array('everyone', 'followers', 'mentioned'), true)) { $privacy = 'everyone'; }
    $errors = array();
    if ($text === '') { $errors['text'] = array("Écrivez quelque chose avant de publier."); }
    elseif (mb_strlen($text) > $max) { $errors['text'] = array("Maximum $max caractères."); }
    if ($errors) { mv1_error(422, 'validation_failed', "Vérifiez le texte.", $errors); }
    mv1_limit('post:' . $mv1['user_id'], 20, 600);

    require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
    $bad = cl_adm_blocked_word($text);
    if ($bad !== '') { mv1_error(422, 'banned_word', "Votre message contient un mot qui n'est pas accepté sur le forum.", array('text' => array("Retirez le mot signalé."))); }

    $thread_data = array();
    if ($thread) {
        $thread_data = cl_raw_post_data($thread);
        if (empty($thread_data)) { mv1_error(404, 'not_found', "Publication introuvable."); }
        $privacy = 'everyone';
    }
    $text = cl_croptxt($text, $max);
    $text = cl_upsert_htags($text);
    $mentions = cl_get_user_mentions($text);
    $post_id = cl_db_insert(T_PUBS, array(
        'user_id' => $me['id'], 'text' => cl_text_secure($text), 'status' => 'active', 'type' => 'text',
        'thread_id' => $thread, 'time' => time(), 'priv_wcs' => $me['profile_privacy'], 'priv_wcr' => $privacy,
    ));
    if (!is_numeric($post_id) || (int) $post_id < 1) { mv1_error(500, 'server_error', "La publication a échoué."); }
    if (!$thread) {
        cl_db_insert(T_POSTS, array('user_id' => $me['id'], 'publication_id' => $post_id, 'time' => time()));
        cl_update_user_data($me['id'], array('posts' => ((int) $me['posts']) + 1));
    } else {
        cl_update_thread_replys($thread, 'plus');
        cl_update_post_data($post_id, array('target' => 'pub_reply'));
        if ($thread_data['user_id'] != $me['id']) {
            cl_notify_user(array('subject' => 'reply', 'user_id' => $thread_data['user_id'], 'entry_id' => $post_id));
        }
    }
    if (!empty($mentions)) { cl_notify_mentioned_users($mentions, $post_id); }
    $raw  = cl_raw_post_data($post_id);
    $out  = array('data' => mv1_post_out(cl_post_data($raw)));
    mv1_idem_end($key, 201, $out);
    mv1_send(201, $out);
}

function mv1_h_post_like($id) {
    global $me;
    mv1_limit('like:' . $me['id'], 120, 600);
    $post = cl_raw_post_data((int) $id);
    if (empty($post)) { mv1_error(404, 'not_found', "Publication introuvable."); }
    $pd = cl_post_data($post);
    if (empty($pd['can_see']) || mv1_is_blocked_either($post['user_id'])) { mv1_error(404, 'not_found', "Publication introuvable."); }
    if (!cl_has_liked($me['id'], (int) $id)) {
        cl_db_insert(T_LIKES, array('pub_id' => (int) $id, 'user_id' => $me['id'], 'time' => time()));
        $n = ((int) $post['likes_count']) + 1;
        cl_update_post_data((int) $id, array('likes_count' => $n));
        if ($post['user_id'] != $me['id']) { cl_notify_user(array('subject' => 'like', 'user_id' => $post['user_id'], 'entry_id' => (int) $id)); }
        mv1_ok(array('liked' => true, 'likes' => $n));
    }
    $n = max(0, ((int) $post['likes_count']) - 1);
    cl_update_post_data((int) $id, array('likes_count' => $n));
    cl_db_delete_item(T_LIKES, array('pub_id' => (int) $id, 'user_id' => $me['id']));
    cl_db_delete_item(T_NOTIFS, array('notifier_id' => $me['id'], 'recipient_id' => $post['user_id'], 'subject' => 'like', 'entry_id' => (int) $id));
    mv1_ok(array('liked' => false, 'likes' => $n));
}

function mv1_h_notifications() {
    global $db;
    require_once(cl_full_path("core/apps/notifications/app_ctrl.php"));
    list($limit, $before) = mv1_page_args(15, 50);
    $type = (isset($_GET['type']) && $_GET['type'] === 'mentions') ? 'mentions' : 'notifs';
    $list = cl_get_notifications(array('type' => $type, 'offset' => $before, 'limit' => $limit));
    $out = array();
    $list = is_array($list) ? $list : array();
    # cl_get_notifications() remplace `time` par un texte relatif : on relit l'horodatage brut.
    $times = array();
    if ($list) {
        $ids = array_map('intval', array_column($list, 'id'));
        foreach ($db->rawQuery("SELECT id, time FROM `" . T_NOTIFS . "` WHERE id IN (" . implode(',', $ids) . ")") as $t) { $times[(int) $t['id']] = (int) $t['time']; }
    }
    foreach ($list as $n) {
        if (!empty($n['notifier_id']) && mv1_is_blocked_either($n['notifier_id'])) { continue; }
        $out[] = array(
            'id'      => isset($n['id']) ? (int) $n['id'] : 0,
            'subject' => isset($n['subject']) ? (string) $n['subject'] : '',
            'entry_id' => isset($n['entry_id']) ? (int) $n['entry_id'] : 0,
            'created_at' => isset($times[(int) $n['id']]) ? $times[(int) $n['id']] : null,
            'seen'    => isset($n['status']) ? ((string) $n['status'] !== '0') : null,
            'actor'   => array(
                'id'       => isset($n['notifier_id']) ? (int) $n['notifier_id'] : 0,
                'username' => isset($n['username']) ? (string) $n['username'] : '',
                'name'     => mv1_plain(isset($n['name']) ? $n['name'] : (isset($n['username']) ? $n['username'] : '')),
                'avatar'   => isset($n['avatar']) ? (string) $n['avatar'] : '',
            ),
        );
    }
    $last = $list ? (int) $list[count($list) - 1]['id'] : null;
    mv1_ok($out, array('limit' => $limit, 'has_more' => count($list) >= $limit, 'next_before' => count($list) >= $limit ? $last : null));
}

/* ------------------------------------------------------------------ Contenus éducatifs et bandes dessinées */

function mv1_edu_boot() {
    require_once(cl_full_path("core/apps/education/app_ctrl.php"));
    cl_edu_install();
}

function mv1_h_edu_list() {
    mv1_edu_boot();
    $cat = isset($_GET['category']) ? (string) $_GET['category'] : '';
    $items = cl_edu_get_items(true, $cat);
    $out = array();
    foreach ($items as $r) {
        $out[] = array(
            'slug' => $r['slug'], 'title' => $r['title'], 'summary' => isset($r['summary']) ? mv1_plain($r['summary']) : '',
            'category' => $r['category'], 'category_label' => $r['category_label'], 'audience' => $r['audience'],
            'image_url' => $r['image_url'], 'minutes' => $r['minutes'], 'pinned' => (bool) $r['pinned'], 'featured' => (bool) $r['pinned'], 'updated_at' => (int) $r['updated_at'],
        );
    }
    # Ordre = celui du web : épinglées (« à la une ») d'abord, puis les plus récemment modifiées.
    mv1_ok($out, array('categories' => cl_edu_categories(), 'total' => count($out), 'featured_count' => count(array_filter($out, function ($x) { return $x['featured']; }))));
}

function mv1_h_edu_get($slug) {
    mv1_edu_boot();
    $r = cl_edu_get_item_by_slug($slug);
    if (!$r) { mv1_error(404, 'not_found', "Fiche introuvable."); }
    $cats = cl_edu_categories();
    $toc = array();
    $html = cl_edu_render_body($r['body'], $toc);
    mv1_ok(array(
        'slug' => $r['slug'], 'title' => $r['title'], 'summary' => isset($r['summary']) ? mv1_plain($r['summary']) : '',
        'category' => $r['category'], 'category_label' => isset($cats[$r['category']]) ? $cats[$r['category']] : $r['category'],
        'audience' => $r['audience'], 'image_url' => cl_edu_image_url($r['image']), 'pinned' => !empty($r['pinned']), 'featured' => !empty($r['pinned']),
        'minutes' => cl_edu_reading_minutes($r['body']), 'updated_at' => (int) $r['updated_at'],
        'body_source' => (string) $r['body'], 'body_html' => $html, 'toc' => $toc,
    ));
}

function mv1_h_comics_list() {
    mv1_edu_boot();
    $out = array();
    foreach (cl_edu_get_comics(true) as $r) {
        $out[] = array(
            'slug' => $r['slug'], 'title' => $r['title'], 'summary' => isset($r['summary']) ? mv1_plain($r['summary']) : '',
            'category' => $r['category'], 'category_label' => $r['category_label'], 'cover_url' => $r['cover_url'],
            'panel_count' => $r['panel_count'], 'has_bubbles' => $r['has_bubbles'], 'pinned' => (bool) $r['pinned'], 'featured' => (bool) $r['pinned'], 'updated_at' => (int) $r['updated_at'],
        );
    }
    mv1_ok($out, array('total' => count($out), 'featured_count' => count(array_filter($out, function ($x) { return $x['featured']; }))));
}

function mv1_h_comics_get($slug) {
    mv1_edu_boot();
    $r = cl_edu_get_comic_by_slug($slug);
    if (!$r) { mv1_error(404, 'not_found', "Bande dessinée introuvable."); }
    $cats = cl_edu_categories();
    $panels = array();
    foreach (cl_edu_panels_decode($r['panels']) as $i => $p) {
        $panels[] = array(
            'number' => $i + 1, 'image_url' => cl_edu_image_url($p['image']), 'alt' => $p['alt'] !== '' ? $p['alt'] : cl_conv_alt($p, $i + 1),
            'caption' => $p['caption'], 'dialogue' => $p['dialogue'], 'bubbles' => $p['bubbles'],
        );
    }
    mv1_ok(array(
        'slug' => $r['slug'], 'title' => $r['title'], 'summary' => isset($r['summary']) ? mv1_plain($r['summary']) : '',
        'category' => $r['category'], 'category_label' => isset($cats[$r['category']]) ? $cats[$r['category']] : $r['category'],
        'pinned' => !empty($r['pinned']), 'featured' => !empty($r['pinned']),
        'updated_at' => (int) $r['updated_at'], 'panels' => $panels,
    ));
}

/* ------------------------------------------------------------------ Alerte VBG (proxy vers ganho.app) */

function mv1_alert_owners() {
    global $mv1;
    $o = array();
    if ($mv1['user_id']) { $o[] = 'u' . $mv1['user_id']; }
    if ($mv1['device_id'] !== '') { $o[] = 'd' . $mv1['device_id']; }
    return $o;
}

function mv1_alert_owns($ticket) {
    global $db;
    $o = mv1_alert_owners();
    if (!$o) { return false; }
    $in = implode(',', array_fill(0, count($o), '?'));
    return (bool) $db->rawQueryOne("SELECT 1 AS x FROM `cl_mobile_alerts` WHERE ticket = ? AND owner IN ($in)", array_merge(array($ticket), $o));
}

function mv1_require_alert_actor() {
    global $mv1;
    if (!$mv1['user_id'] && $mv1['device_id'] === '') {
        mv1_error(403, 'device_required', "Enregistrez d'abord l'appareil (en-tête X-Device-Id).");
    }
}

function mv1_alert_forward($r) {
    $st = (int) $r['status'];
    if ($st >= 200 && $st < 300) { return $r['json']; }
    if ($st === 429) { mv1_error(429, 'too_many_requests', "Trop de demandes. Patientez un instant.", null, array('Retry-After: 60')); }
    if ($st === 422) { mv1_error(422, 'validation_failed', "Vérifiez les champs signalés.", isset($r['json']['errors']) ? $r['json']['errors'] : null); }
    if ($st === 503 || $st === 502 || $st === 0) { mv1_error(503, 'alerts_unavailable', "Le service d'alerte est momentanément indisponible. Réessayez dans un instant."); }
    mv1_error(502, 'upstream_error', "Le service d'alerte n'a pas pu répondre.");
}

function mv1_h_alert_config() {
    require_once(cl_full_path("core/components/ganho_api.php"));
    mv1_ok(mv1_alert_forward(cl_ganho_api_call('GET', '/forum/alert-config')));
}

function mv1_h_alert_list() {
    global $db;
    mv1_require_alert_actor();
    $o = mv1_alert_owners();
    $in = implode(',', array_fill(0, count($o), '?'));
    $rows = $db->rawQuery("SELECT DISTINCT ticket, MAX(created_at) AS created_at FROM `cl_mobile_alerts` WHERE owner IN ($in) GROUP BY ticket ORDER BY created_at DESC LIMIT 50", $o);
    $out = array();
    foreach ($rows as $r) { $out[] = array('ticket' => $r['ticket'], 'created_at' => (int) $r['created_at']); }
    mv1_ok($out);
}

function mv1_h_alert_create() {
    global $db, $mv1, $me;
    require_once(cl_full_path("core/components/ganho_api.php"));
    mv1_require_alert_actor();
    $key = mv1_idem_begin('alerts');
    $in  = mv1_input();
    $payload = array(
        'category' => isset($in['category']) && is_numeric($in['category']) ? (int) $in['category'] : 0,
        'message'  => mv1_str($in, 'message', 10000),
        'email'    => mv1_str($in, 'email', 120),
        'phone'    => mv1_str($in, 'phone', 40),
    );
    if ($payload['category'] < 1) { mv1_error(422, 'validation_failed', "Choisissez une catégorie.", array('category' => array("Choisissez une catégorie."))); }
    if ($payload['message'] === '') { mv1_error(422, 'validation_failed', "Décrivez votre situation.", array('message' => array("Décrivez votre situation."))); }
    if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) { mv1_error(422, 'validation_failed', "Adresse e-mail invalide.", array('email' => array("Adresse e-mail invalide."))); }
    $owner = $mv1['user_id'] ? 'u' . $mv1['user_id'] : 'd' . $mv1['device_id'];
    mv1_limit('alert:' . $owner, 5, 3600);
    mv1_limit('alert-ip:' . mv1_ip(), 15, 3600);
    foreach (array('email', 'phone') as $k) { if ($payload[$k] === '') { unset($payload[$k]); } }
    if ($mv1['user_id'] && !empty($me['username'])) { $payload['forum_user'] = (string) $me['username']; }

    $res = mv1_alert_forward(cl_ganho_api_call('POST', '/forum/alerts', $payload));
    $ticket = isset($res['ticket']) ? (string) $res['ticket'] : '';
    if ($ticket !== '' && preg_match('/^[A-Za-z0-9-]{3,30}$/', $ticket)) {
        $db->rawQuery("REPLACE INTO `cl_mobile_alerts` (owner, ticket, created_at) VALUES (?,?,?)", array($owner, $ticket, time()));
    }
    $out = array('data' => $res);
    mv1_idem_end($key, 201, $out);
    mv1_send(201, $out);
}

function mv1_h_alert_messages($ticket) {
    require_once(cl_full_path("core/components/ganho_api.php"));
    if (!mv1_alert_owns($ticket)) { mv1_error(404, 'not_found', "Alerte introuvable."); }
    mv1_ok(mv1_alert_forward(cl_ganho_api_call('GET', '/forum/alerts/' . rawurlencode($ticket) . '/messages')));
}

function mv1_h_alert_reply($ticket) {
    global $mv1;
    require_once(cl_full_path("core/components/ganho_api.php"));
    if (!mv1_alert_owns($ticket)) { mv1_error(404, 'not_found', "Alerte introuvable."); }
    $key = mv1_idem_begin('alerts/' . $ticket . '/messages');
    $in  = mv1_input();
    $c   = mv1_str($in, 'comment', 10000);
    if ($c === '') { mv1_error(422, 'validation_failed', "Écrivez un message.", array('comment' => array("Écrivez un message."))); }
    mv1_limit('alert-reply:' . ($mv1['user_id'] ?: $mv1['device_id']), 30, 3600);
    $res = mv1_alert_forward(cl_ganho_api_call('POST', '/forum/alerts/' . rawurlencode($ticket) . '/messages', array('comment' => $c)));
    $out = array('data' => $res);
    mv1_idem_end($key, 201, $out);
    mv1_send(201, $out);
}
