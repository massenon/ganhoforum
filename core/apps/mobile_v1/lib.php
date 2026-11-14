<?php
# API mobile v1 : outils (réponses, entrée, jetons, appareils, idempotence, limitation de débit).
# Tables créées à la demande (aucune migration) : cl_mobile_tokens, cl_mobile_devices, cl_mobile_alerts, cl_mobile_idem.

const MV1_TOKEN_DAYS = 30;
const MV1_MAX_BODY   = 65536;

/* ------------------------------------------------------------------ Réponses */

function mv1_send($status, $payload) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code((int) $status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function mv1_ok($data, $meta = null, $status = 200) {
    $out = array('data' => $data);
    if ($meta !== null) { $out['meta'] = $meta; }
    mv1_send($status, $out);
}

function mv1_error($status, $code, $message, $errors = null, $headers = array()) {
    foreach ($headers as $h) { header($h); }
    $out = array('error' => $code, 'message' => $message);
    if ($errors) { $out['errors'] = $errors; }
    mv1_send($status, $out);
}

/* ------------------------------------------------------------------ Entrée */

function mv1_method() { return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'); }

function mv1_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) { return trim((string) $_SERVER[$key]); }
    if ($name === 'Authorization' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']); }
    return '';
}

/** Corps de requête : JSON (Content-Type application/json) ou formulaire. */
function mv1_input() {
    static $in = null;
    if ($in !== null) { return $in; }
    $in = array();
    $len = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($len > MV1_MAX_BODY) { mv1_error(413, 'payload_too_large', "La requête est trop volumineuse."); }
    $ct = strtolower(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '');
    if (strpos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input', false, null, 0, MV1_MAX_BODY + 1);
        if ($raw !== '' && $raw !== false) {
            $j = json_decode($raw, true);
            if (!is_array($j)) { mv1_error(400, 'invalid_json', "Le corps de la requête n'est pas un JSON valide."); }
            $in = $j;
        }
    } else {
        $in = $_POST;
    }
    return $in;
}

function mv1_str($arr, $key, $max = 5000) {
    $v = isset($arr[$key]) && is_scalar($arr[$key]) ? (string) $arr[$key] : '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

function mv1_uuid_ok($s) { return is_string($s) && preg_match('/^[A-Fa-f0-9-]{16,64}$/', $s) === 1; }

function mv1_ip() { return function_exists('cl_get_ip') ? cl_get_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0'); }

/* ------------------------------------------------------------------ Tables */

function mv1_install() {
    global $db;
    static $done = false;
    if ($done) { return; }
    $done = true;
    $db->rawQuery("CREATE TABLE IF NOT EXISTS `cl_mobile_tokens` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `device_id` VARCHAR(64) NOT NULL DEFAULT '',
        `platform` VARCHAR(12) NOT NULL DEFAULT '',
        `created_at` INT UNSIGNED NOT NULL,
        `last_used` INT UNSIGNED NOT NULL,
        `expires_at` INT UNSIGNED NOT NULL,
        `revoked` TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY `uq_token` (`token_hash`), KEY `k_user` (`user_id`), KEY `k_device` (`device_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->rawQuery("CREATE TABLE IF NOT EXISTS `cl_mobile_devices` (
        `device_id` VARCHAR(64) NOT NULL PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `platform` VARCHAR(12) NOT NULL DEFAULT '',
        `app_version` VARCHAR(24) NOT NULL DEFAULT '',
        `push_token` VARCHAR(255) NOT NULL DEFAULT '',
        `created_at` INT UNSIGNED NOT NULL,
        `last_seen` INT UNSIGNED NOT NULL,
        `revoked` TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->rawQuery("CREATE TABLE IF NOT EXISTS `cl_mobile_alerts` (
        `owner` VARCHAR(80) NOT NULL,
        `ticket` VARCHAR(30) NOT NULL,
        `created_at` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`owner`,`ticket`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->rawQuery("CREATE TABLE IF NOT EXISTS `cl_mobile_idem` (
        `k` CHAR(64) NOT NULL PRIMARY KEY,
        `status` SMALLINT NOT NULL,
        `body` MEDIUMTEXT NOT NULL,
        `t` INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->rawQuery("DELETE FROM `cl_mobile_idem` WHERE t < ?", array(time() - 86400));
    $db->rawQuery("DELETE FROM `cl_mobile_tokens` WHERE expires_at < ?", array(time() - 7 * 86400));
}

/* ------------------------------------------------------------------ Contexte et authentification */

function mv1_boot() {
    global $cl, $me, $db, $mv1;
    mv1_install();
    $mv1 = array('user_id' => 0, 'token_id' => 0, 'device_id' => '');
    $dev = mv1_header('X-Device-Id');
    if ($dev !== '' && mv1_uuid_ok($dev)) { $mv1['device_id'] = $dev; }

    $auth = mv1_header('Authorization');
    if ($auth !== '' && preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $auth, $m)) {
        $row = $db->rawQueryOne("SELECT id, user_id, device_id, expires_at, last_used FROM `cl_mobile_tokens` WHERE token_hash = ? AND revoked = 0", array(hash('sha256', strtolower($m[1]))));
        if ($row && (int) $row['expires_at'] > time()) {
            $user = cl_user_data((int) $row['user_id']);
            if (!empty($user) && (string) $user['active'] === '1') {
                $mv1['user_id'] = (int) $row['user_id'];
                $mv1['token_id'] = (int) $row['id'];
                if ($mv1['device_id'] === '' && $row['device_id'] !== '') { $mv1['device_id'] = $row['device_id']; }
                if (time() - (int) $row['last_used'] > 600) {
                    $db->rawQuery("UPDATE `cl_mobile_tokens` SET last_used = ?, expires_at = ? WHERE id = ?", array(time(), time() + MV1_TOKEN_DAYS * 86400, (int) $row['id']));
                }
                $cl['is_logged'] = true;
                $cl['is_admin']  = ((string) $user['admin'] === '1');
                $user['draft_post'] = array();
                $me = $cl['me'] = $user;
            } else {
                mv1_error(401, 'unauthenticated', "Session expirée. Reconnectez-vous.");
            }
        } else {
            mv1_error(401, 'unauthenticated', "Session expirée. Reconnectez-vous.");
        }
    }
}

function mv1_require_auth() {
    global $mv1;
    if (empty($mv1['user_id'])) { mv1_error(401, 'unauthenticated', "Connexion requise."); }
    return $mv1['user_id'];
}

function mv1_new_token($user_id, $device_id, $platform) {
    global $db;
    $token = bin2hex(random_bytes(32));
    $db->rawQuery("INSERT INTO `cl_mobile_tokens` (user_id, token_hash, device_id, platform, created_at, last_used, expires_at) VALUES (?,?,?,?,?,?,?)",
        array((int) $user_id, hash('sha256', $token), (string) $device_id, (string) $platform, time(), time(), time() + MV1_TOKEN_DAYS * 86400));
    return array($token, time() + MV1_TOKEN_DAYS * 86400);
}

/* ------------------------------------------------------------------ Limitation de débit et idempotence */

function mv1_limit($bucket, $max, $window) {
    if (!cl_rate_limit('mv1:' . $bucket, $max, $window)) {
        mv1_error(429, 'too_many_requests', "Trop de demandes. Patientez un instant avant de réessayer.", null, array('Retry-After: ' . $window));
    }
}

/** Idempotence : même clé + même route + même acteur => même réponse, sans second effet. */
function mv1_idem_begin($route) {
    global $db, $mv1;
    $key = mv1_header('Idempotency-Key');
    if ($key === '') { return null; }
    if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $key)) { mv1_error(400, 'invalid_idempotency_key', "Clé d'idempotence invalide."); }
    $owner = $mv1['user_id'] ? 'u' . $mv1['user_id'] : 'd' . $mv1['device_id'];
    $k = hash('sha256', $owner . '|' . $route . '|' . $key);
    $row = $db->rawQueryOne("SELECT status, body FROM `cl_mobile_idem` WHERE k = ?", array($k));
    if ($row) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code((int) $row['status']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('Idempotent-Replay: true');
        echo $row['body'];
        exit;
    }
    return $k;
}

function mv1_idem_end($k, $status, $payload) {
    global $db;
    if ($k === null) { return; }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $db->rawQuery("REPLACE INTO `cl_mobile_idem` (k, status, body, t) VALUES (?,?,?,?)", array($k, (int) $status, $body, time()));
}

/* ------------------------------------------------------------------ Mises en forme */

function mv1_plain($html) {
    $t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[ \t]+/', ' ', $t));
}

function mv1_user_out($u) {
    return array(
        'id'       => (int) $u['id'],
        'username' => (string) $u['username'],
        'name'     => mv1_plain(isset($u['name']) ? $u['name'] : $u['username']),
        'avatar'   => isset($u['avatar']) ? (string) $u['avatar'] : '',
        'verified' => !empty($u['verified']) && (string) $u['verified'] !== '0',
    );
}

function mv1_post_out($p) {
    $media = array();
    foreach ((isset($p['media']) && is_array($p['media'])) ? $p['media'] : array() as $m) {
        $src = isset($m['src']) ? (string) $m['src'] : '';
        if ($src === '') { continue; }
        $media[] = array('type' => isset($m['type']) ? (string) $m['type'] : '', 'url' => function_exists('cl_get_media') ? cl_get_media($src) : $src);
    }
    return array(
        'id'            => (int) $p['id'],
        'type'          => (string) $p['type'],
        'text'          => mv1_plain(isset($p['text']) ? $p['text'] : ''),
        'created_at'    => isset($p['time_raw']) ? (int) $p['time_raw'] : null,
        'author'        => mv1_user_out(isset($p['owner']) ? $p['owner'] : array('id' => 0, 'username' => '')),
        'likes'         => (int) str_replace(array(' ', ',', '.'), '', (string) $p['likes_count']),
        'replies'       => (int) str_replace(array(' ', ',', '.'), '', (string) $p['replys_count']),
        'liked'         => !empty($p['has_liked']),
        'mine'          => !empty($p['is_owner']),
        'media'         => $media,
    );
}
