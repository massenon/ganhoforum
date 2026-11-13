<?php
/**
 * Comptes de TEST du forum (local / recette uniquement) — idempotent :
 *   php core/seeders/test_accounts.php
 *
 * Administrateur : admin.test@ganho.local  (pseudo « admintest »)  / Ganxo#Admin2026   -> /guest (puis « Control panel »)
 * Utilisateur    : user.test@ganho.local   (pseudo « usertest »)   / Ganxo#User2026
 *
 * Refuse de s'exécuter si $site_url n'est pas local (localhost, .localhost, .test) sauf GANHO_ALLOW_TEST_ACCOUNTS=1.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require $root . '/core/settings.php';

$host = (string) parse_url($site_url, PHP_URL_HOST);
$local = (bool) preg_match('/(^localhost$|\.localhost$|\.test$|^127\.0\.0\.1$)/i', $host);
if (!$local && getenv('GANHO_ALLOW_TEST_ACCOUNTS') !== '1') {
    fwrite(STDERR, "Refusé : \$site_url ($site_url) n'est pas un environnement local.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);
$db->set_charset('utf8mb4');

$cfg = [];
foreach ($db->query("SELECT `name`,`value` FROM cl_configs WHERE `name` IN ('language','default_color_scheme','default_bg_color','country_id')") as $r) {
    $cfg[$r['name']] = $r['value'];
}
$lang = $cfg['language'] ?? 'default';
$display = json_encode(['color_scheme' => $cfg['default_color_scheme'] ?? 'default', 'background' => 'default']);

$accounts = [
    ['username' => 'admintest', 'fname' => 'Admin', 'lname' => 'Test', 'email' => 'admin.test@ganho.local', 'pass' => 'Ganxo#Admin2026', 'admin' => '1'],
    ['username' => 'usertest', 'fname' => 'Utilisateur', 'lname' => 'Test', 'email' => 'user.test@ganho.local', 'pass' => 'Ganxo#User2026', 'admin' => '0'],
];

foreach ($accounts as $a) {
    $hash = password_hash($a['pass'], PASSWORD_DEFAULT);
    $now = (string) time();
    $find = $db->prepare('SELECT id FROM cl_users WHERE email = ? LIMIT 1');
    $find->bind_param('s', $a['email']);
    $find->execute();
    $row = $find->get_result()->fetch_assoc();

    if ($row) {
        $u = $db->prepare("UPDATE cl_users SET username=?, fname=?, lname=?, password=?, admin=?, active='1', em_code='', email_conf_code='0' WHERE id=?");
        $u->bind_param('sssssi', $a['username'], $a['fname'], $a['lname'], $hash, $a['admin'], $row['id']);
        $u->execute();
        $id = $row['id'];
        $verb = 'mis à jour';
    } else {
        $dup = $db->prepare('SELECT id FROM cl_users WHERE username = ? LIMIT 1');
        $dup->bind_param('s', $a['username']);
        $dup->execute();
        if ($dup->get_result()->num_rows) {
            fwrite(STDERR, "Le pseudo {$a['username']} existe déjà avec un autre e-mail : abandon.\n");
            exit(1);
        }
        $country = (int) ($cfg['country_id'] ?? 1);
        $start = json_encode(['source' => 'system', 'avatar' => 1, 'info' => 1, 'follow' => 1]);
        $i = $db->prepare("INSERT INTO cl_users (username, fname, lname, password, email, admin, active, em_code, last_active, joined, start_up, ip_address, country_id, language, display_settings) VALUES (?,?,?,?,?,?, '1', '', ?, ?, ?, '127.0.0.1', ?, ?, ?)");
        $i->bind_param('sssssssssiss', $a['username'], $a['fname'], $a['lname'], $hash, $a['email'], $a['admin'], $now, $now, $start, $country, $lang, $display);
        $i->execute();
        $id = $db->insert_id;
        $verb = 'créé';
    }
    echo "#{$id} {$a['email']} ({$a['username']}) {$verb} — mot de passe : {$a['pass']}\n";
}

// Fil d'actualité non vide en local : les comptes de test suivent les 5 comptes les plus actifs (publications).
$top = $db->query("SELECT user_id FROM cl_posts GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 5");
$topIds = [];
while ($r = $top->fetch_assoc()) { $topIds[] = (int) $r['user_id']; }
$tests = $db->query("SELECT id FROM cl_users WHERE username IN ('admintest','usertest')");
while ($t = $tests->fetch_assoc()) {
    foreach ($topIds as $fid) {
        if ($fid == (int) $t['id']) { continue; }
        $db->query("INSERT INTO cl_connections (follower_id, following_id, status, time) SELECT {$t['id']}, {$fid}, 'active', UNIX_TIMESTAMP() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cl_connections WHERE follower_id={$t['id']} AND following_id={$fid})");
    }
    $db->query("UPDATE cl_users SET following=(SELECT COUNT(*) FROM cl_connections WHERE follower_id={$t['id']} AND status='active') WHERE id={$t['id']}");
}
foreach ($topIds as $fid) {
    $db->query("UPDATE cl_users SET followers=(SELECT COUNT(*) FROM cl_connections WHERE following_id={$fid} AND status='active') WHERE id={$fid}");
}
echo "Comptes de test : abonnés aux 5 comptes les plus actifs (" . implode(',', $topIds) . ").\n";
