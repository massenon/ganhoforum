<?php
# @*************************************************************************@
# @ Ganho Forum : campagnes e-mail (diffusion d'informations) et modèles     @
# @ Tables créées à la demande (cl_cmp_install) : aucune migration à lancer. @
# @*************************************************************************@

if (!defined("T_MAIL_TEMPLATES")) { define("T_MAIL_TEMPLATES", "cl_mail_templates"); }
if (!defined("T_MAIL_CAMPAIGNS")) { define("T_MAIL_CAMPAIGNS", "cl_mail_campaigns"); }
if (!defined("T_MAIL_LOG"))       { define("T_MAIL_LOG", "cl_mail_log"); }
if (!defined("T_MAIL_OPTOUT"))    { define("T_MAIL_OPTOUT", "cl_mail_optout"); }

/** Variables dynamiques proposées dans l'éditeur de modèles. */
function cl_cmp_variables() {
	return array(
		array("{{prenom}}", "Prénom (ou nom d'utilisateur)"),
		array("{{nom_utilisateur}}", "Nom d'utilisateur"),
		array("{{lien_forum}}", "Lien du forum"),
		array("{{date_evenement}}", "Date de l'événement")
	);
}

function cl_cmp_audiences() {
	return array(
		"all"        => "Tous les utilisateurs",
		"moderators" => "Modérateurs et administrateurs",
		"active30"   => "Membres actifs (30 derniers jours)",
		"verified"   => "Comptes vérifiés",
		"new30"      => "Nouveaux membres (30 derniers jours)"
	);
}

function cl_cmp_statuses() {
	return array("draft" => "Brouillon", "scheduled" => "Programmé", "sending" => "En cours", "sent" => "Envoyé");
}

/** Crée les tables si besoin et ajoute trois modèles de départ. */
function cl_cmp_install() {
	global $db;
	static $done = false;

	if ($done) { return true; }
	$done = true;

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_MAIL_TEMPLATES . "` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`name` VARCHAR(120) NOT NULL,
		`subject` VARCHAR(190) NOT NULL DEFAULT '',
		`preheader` VARCHAR(190) NOT NULL DEFAULT '',
		`body` TEXT NOT NULL,
		`cta_label` VARCHAR(60) NOT NULL DEFAULT '',
		`cta_url` VARCHAR(255) NOT NULL DEFAULT '',
		`kind` VARCHAR(16) NOT NULL DEFAULT 'news',
		`created_at` INT UNSIGNED NOT NULL DEFAULT 0,
		`updated_at` INT UNSIGNED NOT NULL DEFAULT 0
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_MAIL_CAMPAIGNS . "` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`title` VARCHAR(190) NOT NULL,
		`subject` VARCHAR(190) NOT NULL DEFAULT '',
		`preheader` VARCHAR(190) NOT NULL DEFAULT '',
		`template_id` INT UNSIGNED NOT NULL DEFAULT 0,
		`body` TEXT NULL,
		`event_date` VARCHAR(60) NOT NULL DEFAULT '',
		`audience` VARCHAR(20) NOT NULL DEFAULT 'all',
		`status` VARCHAR(12) NOT NULL DEFAULT 'draft',
		`scheduled_at` INT UNSIGNED NULL,
		`sent_at` INT UNSIGNED NULL,
		`total` INT UNSIGNED NOT NULL DEFAULT 0,
		`sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
		`failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
		`open_count` INT UNSIGNED NOT NULL DEFAULT 0,
		`created_at` INT UNSIGNED NOT NULL DEFAULT 0,
		`updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
		KEY `status` (`status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_MAIL_LOG . "` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`campaign_id` INT UNSIGNED NOT NULL,
		`user_id` INT UNSIGNED NOT NULL,
		`email` VARCHAR(120) NOT NULL,
		`token` CHAR(32) NOT NULL,
		`status` VARCHAR(8) NOT NULL DEFAULT 'queued',
		`sent_at` INT UNSIGNED NULL,
		`opened_at` INT UNSIGNED NULL,
		KEY `campaign_status` (`campaign_id`,`status`),
		UNIQUE KEY `token` (`token`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_MAIL_OPTOUT . "` (`user_id` INT UNSIGNED NOT NULL PRIMARY KEY, `t` INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$n = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_MAIL_TEMPLATES . "`");
	if (empty($n["n"])) {
		$now = time();
		$seed = array(
			array("Newsletter hebdo", "news", "Les nouvelles de la semaine sur GANXÔ", "Ce qui s'est passé cette semaine",
				"Bonjour {{prenom}},\n\nVoici les nouvelles de la semaine sur le forum GANXÔ : nouveaux échanges, ressources et rendez-vous à ne pas manquer.\n\nProchain rendez-vous : {{date_evenement}}.\n\nÀ très vite sur le forum,\nL'équipe GANXÔ", "Ouvrir le forum", "{{lien_forum}}"),
			array("Alerte maintenance", "alert", "Maintenance du forum GANXÔ", "Le forum sera brièvement indisponible",
				"Bonjour {{prenom}},\n\nLe forum sera en maintenance le {{date_evenement}}. Il peut être indisponible quelques minutes pendant cette opération.\n\nMerci de votre patience.\nL'équipe GANXÔ", "", ""),
			array("Bienvenue membre", "welcome", "Bienvenue sur le forum GANXÔ", "Votre place est ici",
				"Bonjour {{prenom}},\n\nBienvenue dans la communauté GANXÔ. Présentez-vous, échangez, partagez : le forum est un espace de parole bienveillant et modéré.\n\nVotre nom d'utilisateur : {{nom_utilisateur}}.\n\nPour vous orienter si besoin, vous pouvez à tout moment créer une alerte VBG depuis le menu.", "Rejoindre la conversation", "{{lien_forum}}")
		);
		foreach ($seed as $s) {
			$db->insert(T_MAIL_TEMPLATES, array("name" => $s[0], "kind" => $s[1], "subject" => $s[2], "preheader" => $s[3], "body" => $s[4], "cta_label" => $s[5], "cta_url" => $s[6], "created_at" => $now, "updated_at" => $now));
		}
	}

	return true;
}

/* ---------------------------------------------------------------- Audiences */

function cl_cmp_audience_where($audience) {
	$t = time() - 30 * 86400;
	$w = "active = '1' AND email <> '' AND id NOT IN (SELECT user_id FROM `" . T_MAIL_OPTOUT . "`)";

	if ($audience == "moderators") { $w .= " AND admin = '1'"; }
	else if ($audience == "active30") { $w .= " AND CAST(last_active AS UNSIGNED) > " . (int) $t; }
	else if ($audience == "verified") { $w .= " AND verified = '1'"; }
	else if ($audience == "new30") { $w .= " AND CAST(joined AS UNSIGNED) > " . (int) $t; }

	return $w;
}

function cl_cmp_audience_count($audience) {
	global $db;
	$r = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_USERS . "` WHERE " . cl_cmp_audience_where($audience));
	return (int) (isset($r["n"]) ? $r["n"] : 0);
}

/* ---------------------------------------------------------------- Rendu */

function cl_cmp_h($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
}

function cl_cmp_user_vars($user, $campaign = array()) {
	global $cl;
	$first = trim(isset($user["fname"]) ? $user["fname"] : "");
	if ($first === "") { $first = isset($user["username"]) ? $user["username"] : ""; }

	return array(
		"{{prenom}}"           => $first,
		"{{nom_utilisateur}}"  => isset($user["username"]) ? $user["username"] : "",
		"{{lien_forum}}"       => cl_link("/"),
		"{{date_evenement}}"   => (!empty($campaign["event_date"]) ? $campaign["event_date"] : "(date à préciser)")
	);
}

function cl_cmp_safe_url($url) {
	$url = trim((string) $url);
	if ($url === "" || $url === "{{lien_forum}}") { return $url; }
	return preg_match('~^(https?://|mailto:)~i', $url) ? $url : "";
}

/**
 * Rend l'e-mail complet (HTML) : habillage Ganho, texte du modèle (ou de la campagne), bouton, pixel d'ouverture.
 * $tpl = ligne de cl_mail_templates ; $campaign peut surcharger objet, pré-en-tête et corps.
 */
function cl_cmp_render($tpl, $campaign, $user, $token = "") {
	$vars    = cl_cmp_user_vars($user, $campaign);
	$subject = !empty($campaign["subject"]) ? $campaign["subject"] : (isset($tpl["subject"]) ? $tpl["subject"] : "");
	$pre     = !empty($campaign["preheader"]) ? $campaign["preheader"] : (isset($tpl["preheader"]) ? $tpl["preheader"] : "");
	$body    = (isset($campaign["body"]) && trim((string) $campaign["body"]) !== "") ? $campaign["body"] : (isset($tpl["body"]) ? $tpl["body"] : "");
	$ctaL    = isset($tpl["cta_label"]) ? $tpl["cta_label"] : "";
	$ctaU    = cl_cmp_safe_url(isset($tpl["cta_url"]) ? $tpl["cta_url"] : "");

	$esc = function ($s) use ($vars) {
		$s = cl_cmp_h($s);
		foreach ($vars as $k => $v) { $s = str_replace($k, cl_cmp_h($v), $s); }
		return $s;
	};

	$paras = "";
	foreach (preg_split('/\R{2,}/', trim((string) $body)) as $p) {
		if (trim($p) === "") { continue; }
		$paras .= '<p style="margin:0 0 18px 0;font-size:16px;line-height:1.6;color:#17132B;">' . nl2br($esc($p), false) . '</p>';
	}

	$button = "";
	if ($ctaL !== "" && $ctaU !== "") {
		$href   = str_replace(array_keys($vars), array_values($vars), $ctaU);
		$button = '<p style="margin:26px 0 0 0;"><a href="' . cl_cmp_h($href) . '" style="display:inline-block;padding:14px 32px;border-radius:999px;background:#2B33C9;color:#FFFFFF;font-family:Montserrat,Arial,sans-serif;font-size:15px;font-weight:700;text-decoration:none;">' . $esc($ctaL) . '</a></p>';
	}

	$pixel = ($token !== "") ? '<img src="' . cl_cmp_h(cl_link("mail_open.php?t=" . $token)) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">' : "";
	$logo  = cl_link("themes/default/statics/ganho/img/logo-lettres-blanches-416w.png");
	$spec  = array("#5B2BB8", "#2B33C9", "#0A66E8", "#0F8B6D", "#3F9A1A", "#FFBE05", "#F66100", "#E8381A");
	$bands = "";
	foreach ($spec as $c) { $bands .= '<td height="6" style="height:6px;background:' . $c . ';font-size:0;line-height:0;">&nbsp;</td>'; }

	$html  = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $esc($subject) . '</title></head><body style="margin:0;padding:0;background:#F8F1FF;">';
	$html .= '<span style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $esc($pre) . '</span>';
	$html .= '<div style="margin:0;padding:24px 12px;background:#F8F1FF;font-family:Montserrat,Arial,\'Liberation Sans\',Roboto,sans-serif;">';
	$html .= '<table role="presentation" width="600" align="center" style="width:600px;max-width:100%;border-collapse:collapse;border:0;margin:0 auto;">';
	$html .= '<tr><td align="center" style="padding:30px 24px 26px 24px;background:#0B0B14;border-radius:24px 24px 0 0;"><img src="' . cl_cmp_h($logo) . '" alt="GANXÔ" width="150" style="display:block;border:0;width:150px;height:auto;"></td></tr>';
	$html .= '<tr><td style="padding:0;font-size:0;line-height:0;"><table role="presentation" width="100%" style="width:100%;border-collapse:collapse;"><tr>' . $bands . '</tr></table></td></tr>';
	$html .= '<tr><td style="padding:36px 32px 32px 32px;background:#FFFFFF;color:#17132B;"><h1 style="margin:0 0 22px 0;font-family:Montserrat,Arial,sans-serif;font-size:24px;line-height:1.25;font-weight:800;color:#0B0B14;">' . $esc($subject) . '</h1>' . $paras . $button . '</td></tr>';
	$html .= '<tr><td align="center" style="padding:26px 24px;background:#0B0B14;border-radius:0 0 24px 24px;"><p style="margin:0 0 8px 0;font-size:13px;line-height:1.5;color:#B9B4D0;">Vous recevez ce message parce que vous êtes membre du forum GANXÔ. ' . ($token !== "" ? '<a href="' . cl_cmp_h(cl_link("mail_unsub.php?t=" . $token)) . '" style="color:#8FA2FF;">Ne plus recevoir ces informations</a>' : '<span style="color:#8FA2FF;">Un lien de désinscription personnel est ajouté à chaque envoi.</span>') . '</p><p style="margin:0;font-size:12px;line-height:1.5;color:#B9B4D0;">&copy; ' . date("Y") . ' GANXÔ &middot; <a href="' . cl_cmp_h(cl_link("/")) . '" style="color:#8FA2FF;text-decoration:none;">' . cl_cmp_h(cl_link("/")) . '</a></p></td></tr>';
	$html .= '</table>' . $pixel . '</div></body></html>';

	return array("subject" => strtr($subject, $vars), "html" => $html);
}

/* ---------------------------------------------------------------- Accès aux lignes */

function cl_cmp_get_template($id) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_MAIL_TEMPLATES . "` WHERE id = ?", array((int) $id));
	return $r ? $r : array();
}

function cl_cmp_get_campaign($id) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_MAIL_CAMPAIGNS . "` WHERE id = ?", array((int) $id));
	return $r ? $r : array();
}

function cl_cmp_templates() {
	global $db;
	$rows = $db->rawQuery("SELECT * FROM `" . T_MAIL_TEMPLATES . "` ORDER BY updated_at DESC, id DESC");
	foreach ($rows as &$r) {
		$r["id"]         = (int) $r["id"];
		$r["updated_fr"] = date("d/m/Y", (int) $r["updated_at"]);
	}
	return $rows;
}

function cl_cmp_campaigns() {
	global $db;
	$rows = $db->rawQuery("SELECT c.*, t.name AS template_name FROM `" . T_MAIL_CAMPAIGNS . "` c LEFT JOIN `" . T_MAIL_TEMPLATES . "` t ON t.id = c.template_id ORDER BY c.id DESC");
	$aud = cl_cmp_audiences();
	foreach ($rows as &$r) {
		foreach (array("id", "template_id", "total", "sent_count", "failed_count", "open_count") as $k) { $r[$k] = (int) $r[$k]; }
		$r["audience_label"] = isset($aud[$r["audience"]]) ? $aud[$r["audience"]] : $r["audience"];
		$r["template_name"]  = $r["template_name"] ? $r["template_name"] : "(modèle supprimé)";
		$r["date_fr"]        = $r["sent_at"] ? date("d/m/Y H:i", (int) $r["sent_at"]) : ($r["scheduled_at"] ? date("d/m/Y H:i", (int) $r["scheduled_at"]) : "—");
		$r["scheduled_input"] = $r["scheduled_at"] ? date("Y-m-d\TH:i", (int) $r["scheduled_at"]) : "";
	}
	return $rows;
}

function cl_cmp_kpis() {
	global $db;
	$r = $db->rawQueryOne("SELECT COALESCE(SUM(sent_count),0) AS sent, COALESCE(SUM(open_count),0) AS opens FROM `" . T_MAIL_CAMPAIGNS . "` WHERE status IN ('sent','sending')");
	$s = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_MAIL_CAMPAIGNS . "` WHERE status = 'scheduled'");
	$sent = (int) $r["sent"];
	return array(
		"sent"      => $sent,
		"open_rate" => $sent > 0 ? round(100 * (int) $r["opens"] / $sent) : 0,
		"scheduled" => (int) $s["n"]
	);
}

/* ---------------------------------------------------------------- Envoi */

/** Vrai si le serveur SMTP répond (évite d'attendre 30 s par message quand il est injoignable). */
function cl_cmp_smtp_ok() {
	global $cl;
	if (isset($cl["config"]["smtp_or_mail"]) && $cl["config"]["smtp_or_mail"] == "mail") { return true; }
	$host = isset($cl["config"]["smtp_host"]) ? $cl["config"]["smtp_host"] : "";
	$port = (int) (isset($cl["config"]["smtp_port"]) ? $cl["config"]["smtp_port"] : 0);
	if ($host === "" || $port < 1) { return false; }
	$s = @fsockopen(($cl["config"]["smtp_encryption"] == "ssl" ? "ssl://" : "") . $host, $port, $en, $es, 5);
	if ($s) { fclose($s); return true; }
	return false;
}

function cl_cmp_mail($to_email, $to_name, $subject, $html) {
	global $cl;
	return cl_send_mail(array(
		"from_email"   => $cl["config"]["email"],
		"from_name"    => $cl["config"]["name"],
		"to_email"     => $to_email,
		"to_name"      => $to_name,
		"subject"      => $subject,
		"charSet"      => "UTF-8",
		"is_html"      => true,
		"message_body" => $html
	));
}

/** Prépare la file d'envoi (une ligne par destinataire) puis passe la campagne en « En cours ». */
function cl_cmp_start($cid) {
	global $db;
	$c = cl_cmp_get_campaign($cid);
	if (!$c) { return false; }

	$exists = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_MAIL_LOG . "` WHERE campaign_id = ?", array((int) $cid));
	if (empty($exists["n"])) {
		$users = $db->rawQuery("SELECT id, email FROM `" . T_USERS . "` WHERE " . cl_cmp_audience_where($c["audience"]));
		foreach ($users as $u) {
			$db->insert(T_MAIL_LOG, array("campaign_id" => (int) $cid, "user_id" => (int) $u["id"], "email" => $u["email"], "token" => md5($cid . "|" . $u["id"] . "|" . microtime(true) . mt_rand()), "status" => "queued"));
		}
		$db->rawQuery("UPDATE `" . T_MAIL_CAMPAIGNS . "` SET total = ?, sent_count = 0, failed_count = 0, open_count = 0, status = 'sending', updated_at = ? WHERE id = ?", array(count($users), time(), (int) $cid));
	} else {
		$db->rawQuery("UPDATE `" . T_MAIL_CAMPAIGNS . "` SET status = 'sending', updated_at = ? WHERE id = ?", array(time(), (int) $cid));
	}
	return true;
}

/** Envoie jusqu'à $limit messages en file ; renvoie l'état d'avancement. */
function cl_cmp_process($cid, $limit = 20) {
	global $db;
	$c = cl_cmp_get_campaign($cid);
	if (!$c) { return array("done" => true, "error" => "unknown"); }

	$tpl  = cl_cmp_get_template($c["template_id"]);
	$rows = $db->rawQuery("SELECT l.id, l.token, l.email, u.username, u.fname FROM `" . T_MAIL_LOG . "` l LEFT JOIN `" . T_USERS . "` u ON u.id = l.user_id WHERE l.campaign_id = ? AND l.status = 'queued' ORDER BY l.id LIMIT " . (int) $limit, array((int) $cid));

	foreach ($rows as $r) {
		$msg = cl_cmp_render($tpl, $c, $r, $r["token"]);
		$ok  = cl_cmp_mail($r["email"], $r["username"], $msg["subject"], $msg["html"]);
		$db->rawQuery("UPDATE `" . T_MAIL_LOG . "` SET status = ?, sent_at = ? WHERE id = ?", array($ok ? "sent" : "failed", time(), (int) $r["id"]));
	}

	$st = $db->rawQueryOne("SELECT SUM(status='sent') AS s, SUM(status='failed') AS f, SUM(status='queued') AS q FROM `" . T_MAIL_LOG . "` WHERE campaign_id = ?", array((int) $cid));
	$q  = (int) $st["q"];
	$db->rawQuery("UPDATE `" . T_MAIL_CAMPAIGNS . "` SET sent_count = ?, failed_count = ?, status = ?, sent_at = ?, updated_at = ? WHERE id = ?", array((int) $st["s"], (int) $st["f"], $q > 0 ? "sending" : "sent", $q > 0 ? null : time(), time(), (int) $cid));

	return array("done" => $q == 0, "sent" => (int) $st["s"], "failed" => (int) $st["f"], "queued" => $q, "total" => (int) $c["total"]);
}

function cl_cmp_open($token) {
	global $db;
	$r = $db->rawQueryOne("SELECT id, campaign_id, opened_at FROM `" . T_MAIL_LOG . "` WHERE token = ?", array(substr(preg_replace('/[^a-f0-9]/', '', (string) $token), 0, 32)));
	if ($r && empty($r["opened_at"])) {
		$db->rawQuery("UPDATE `" . T_MAIL_LOG . "` SET opened_at = ? WHERE id = ?", array(time(), (int) $r["id"]));
		$db->rawQuery("UPDATE `" . T_MAIL_CAMPAIGNS . "` SET open_count = open_count + 1 WHERE id = ?", array((int) $r["campaign_id"]));
	}
}
