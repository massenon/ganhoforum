<?php
# Boîte à suggestions : enregistre une suggestion (membres connectés, 5 par jour au plus).
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
$data['status'] = 400;
if (empty($cl['is_logged'])) {
	$data['message'] = "Connectez-vous pour envoyer une suggestion.";
}
else if ($action == 'warn_seen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	cl_adm_install();
	foreach (array_filter(array_map('intval', explode(',', (string) fetch_or_get($_POST['ids'], '')))) as $sid) {
		// seulement ses propres avertissements
		$own = $db->rawQueryOne("SELECT id FROM `" . T_ADM_SANCTIONS . "` WHERE id = ? AND user_id = ? AND type = 'warn'", array($sid, (int) $me['id']));
		if ($own) { $db->rawQuery("REPLACE INTO `cl_adm_warn_seen` (sanction_id, t) VALUES (?, ?)", array($sid, time())); }
	}
	$data['status'] = 200;
}
else if ($action == 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	cl_adm_install();
	$subject = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) fetch_or_get($_POST['subject'], '')))), 0, 160);
	$message = mb_substr(trim(strip_tags(str_replace("\r\n", "\n", (string) fetch_or_get($_POST['message'], '')))), 0, 2000);
	$today   = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_ADM_SUGGESTIONS . "` WHERE user_id = ? AND created_at > ?", array((int) $me['id'], time() - 86400));
	if ($subject === '' || mb_strlen($message) < 10) { $data['message'] = "Indiquez un sujet et un message d’au moins 10 caractères."; }
	else if ((int) $today['n'] >= 5) { $data['message'] = "Vous avez atteint la limite de 5 suggestions par jour. Merci !"; }
	else {
		$db->insert(T_ADM_SUGGESTIONS, array("user_id" => (int) $me['id'], "subject" => $subject, "message" => $message, "status" => "new", "created_at" => time(), "updated_at" => time()));
		$data['status'] = 200;
	}
}
