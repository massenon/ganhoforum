<?php
# Envoie les campagnes programmées dont l'heure est passée. À lancer chaque minute (cron) :
#   * * * * * php /chemin/vers/forumganho/core/cron/send_campaigns.php
if (php_sapi_name() !== "cli") { http_response_code(404); exit; }
chdir(dirname(__DIR__, 2));
$_SERVER["HTTP_HOST"] = $_SERVER["HTTP_HOST"] ?? "localhost";
require_once("core/web_req_init.php");
require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
cl_cmp_install();
if (!cl_cmp_smtp_ok()) { fwrite(STDERR, "SMTP injoignable\n"); exit(1); }
$due = $db->rawQuery("SELECT id, status FROM `" . T_MAIL_CAMPAIGNS . "` WHERE (status = 'scheduled' AND scheduled_at <= ?) OR status = 'sending'", array(time()));
foreach ($due as $c) {
	if ($c["status"] == "scheduled") { cl_cmp_start((int) $c["id"]); }
	do { $r = cl_cmp_process((int) $c["id"], 50); } while (empty($r["done"]));
	echo "Campagne " . $c["id"] . " : " . $r["sent"] . " envoyé(s), " . $r["failed"] . " échec(s)\n";
}
