<?php
# API mobile du forum GANXÔ, version 1 : https://forum.ganho.app/api/mobile/v1/*
# Contrat : docs/mobile/API_FORUM_MOBILE.md (dépôt de documentation) et core/apps/mobile_v1/routes.php.
# Authentification : jeton Bearer UNIQUEMENT (jamais de cookie ni de session_id dans l'URL).

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

# Un cookie de navigateur ne doit jamais authentifier une requête mobile (pas de CSRF) : on l'ignore.
$_COOKIE = array();
unset($_POST['session_id'], $_GET['session_id']);

require_once(__DIR__ . "/core/apps/mobile_v1/lib.php");
require_once(__DIR__ . "/core/apps/mobile_v1/routes.php");
require_once(__DIR__ . "/core/apps/mobile_v1/routes_v11.php");

try {
    require_once(__DIR__ . "/core/api_req_init.php");
    ini_set('display_errors', '0');
    mv1_boot();
    mv1_dispatch();
} catch (Throwable $e) {
    error_log('[mobile_v1] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    mv1_error(500, 'server_error', "Une erreur est survenue. Réessayez dans un instant.");
}
