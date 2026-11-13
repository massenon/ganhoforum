<?php
# Client de l'API serveur-a-serveur GANXO Alerte VBG (ganho.app) : signature HMAC-SHA256.
# Configuration dans core/settings.php : $ganho_api_url, $ganho_api_secret.

function cl_ganho_api_cfg() {
    global $ganho_api_url, $ganho_api_secret;
    $url    = getenv('GANHO_API_URL') ?: (isset($ganho_api_url) ? $ganho_api_url : '');
    $secret = getenv('GANHO_API_SECRET') ?: (isset($ganho_api_secret) ? $ganho_api_secret : '');
    return array(rtrim($url, '/'), $secret);
}

/** Appelle l'API ; retourne array('status' => int, 'json' => array|null). */
function cl_ganho_api_call($method, $path, $payload = null) {
    list($base, $secret) = cl_ganho_api_cfg();
    if ($base === '' || strlen($secret) < 32) {
        return array('status' => 503, 'json' => array('error' => 'forum_api_not_configured'));
    }
    $method = strtoupper($method);
    $url    = $base . $path;
    $body   = ($payload === null) ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ts     = (string) time();
    $p      = '/' . ltrim(parse_url($url, PHP_URL_PATH), '/');
    $sig    = hash_hmac('sha256', $ts . "\n" . $method . "\n" . $p . "\n" . hash('sha256', $body), $secret);

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json', 'Accept: application/json',
            'X-Forum-Timestamp: ' . $ts, 'X-Forum-Signature: ' . $sig,
        ),
    ));
    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $out    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($out === false) {
        return array('status' => 502, 'json' => array('error' => 'unreachable'));
    }
    $json = json_decode($out, true);
    return array('status' => $status, 'json' => is_array($json) ? $json : null);
}

# --- Suivi des alertes de l'appareil : cookie signe (fonctionne sans compte) ---
function cl_gn_alerts_read() {
    list($b, $secret) = cl_ganho_api_cfg();
    $raw = isset($_COOKIE['gn_alerts']) ? (string) $_COOKIE['gn_alerts'] : '';
    if ($raw === '' || strpos($raw, '.') === false || $secret === '') {
        return array();
    }
    list($enc, $mac) = explode('.', $raw, 2);
    if (!hash_equals(hash_hmac('sha256', $enc, $secret), $mac)) {
        return array();
    }
    $list = json_decode(base64_decode(strtr($enc, '-_', '+/')), true);
    if (!is_array($list)) {
        return array();
    }
    return array_values(array_filter($list, function ($t) { return is_string($t) && preg_match('/^[A-Za-z0-9-]{3,30}$/', $t); }));
}

function cl_gn_alerts_add($ticket) {
    list($b, $secret) = cl_ganho_api_cfg();
    $list = cl_gn_alerts_read();
    $list[] = $ticket;
    $list = array_slice(array_values(array_unique($list)), -5);
    $enc  = rtrim(strtr(base64_encode(json_encode($list)), '+/', '-_'), '=');
    $val  = $enc . '.' . hash_hmac('sha256', $enc, $secret);
    setcookie('gn_alerts', $val, array('expires' => time() + 86400 * 90, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'));
    $_COOKIE['gn_alerts'] = $val;
}
