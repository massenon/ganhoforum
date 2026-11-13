<?php
# Alerte VBG en chat : proxy serveur du forum vers l'API de GANXO Alerte VBG (ganho.app).
require_once(cl_full_path("core/components/ganho_api.php"));

$data['status'] = 400;
$data['err_code'] = 'invalid_req';

$ticket_ok = function ($t) {
    return is_string($t) && in_array($t, cl_gn_alerts_read(), true);
};
$reply = function ($r) use (&$data) {
    $data['status'] = ($r['status'] >= 200 && $r['status'] < 300) ? 200 : (int) $r['status'];
    $data['api'] = $r['json'];
    unset($data['err_code']);
};

if ($action == 'config') {
    $r = cl_ganho_api_call('GET', '/forum/alert-config');
    $reply($r);
    $data['tickets'] = cl_gn_alerts_read();
}
elseif ($action == 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = array(
        'category'   => (int) fetch_or_get($_POST['category'], 0),
        'message'    => trim((string) fetch_or_get($_POST['message'], '')),
        'email'      => trim((string) fetch_or_get($_POST['email'], '')),
        'phone'      => trim((string) fetch_or_get($_POST['phone'], '')),
        'forum_user' => (!empty($cl['is_logged']) && !empty($me['username'])) ? (string) $me['username'] : '',
    );
    foreach (array('email', 'phone', 'forum_user') as $k) {
        if ($payload[$k] === '') { unset($payload[$k]); }
    }
    $r = cl_ganho_api_call('POST', '/forum/alerts', $payload);
    if ($r['status'] === 200 && !empty($r['json']['ticket'])) {
        cl_gn_alerts_add($r['json']['ticket']);
    }
    $reply($r);
}
elseif ($action == 'messages') {
    $t = fetch_or_get($_GET['ticket'], '');
    if (!$ticket_ok($t)) {
        $data['status'] = 403;
    } else {
        $reply(cl_ganho_api_call('GET', '/forum/alerts/' . rawurlencode($t) . '/messages'));
    }
}
elseif ($action == 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = fetch_or_get($_POST['ticket'], '');
    if (!$ticket_ok($t)) {
        $data['status'] = 403;
    } else {
        $reply(cl_ganho_api_call('POST', '/forum/alerts/' . rawurlencode($t) . '/messages', array(
            'comment' => trim((string) fetch_or_get($_POST['comment'], '')),
        )));
    }
}
