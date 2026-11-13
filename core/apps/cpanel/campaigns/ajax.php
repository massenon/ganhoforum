<?php
# @*************************************************************************@
# @ Ganho Forum : actions AJAX des campagnes e-mail (admin uniquement).      @
# @ Appelé depuis apps/native/ajax/cpanel/content.php pour les actions cmp_* @
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
cl_cmp_install();

$data["status"] = 400;
$aud            = cl_cmp_audiences();
$now            = time();

/** Texte court sans balises ni retours à la ligne. */
$cmp_line = function ($v, $max) {
	$v = trim(strip_tags((string) $v));
	$v = preg_replace('/\s+/', ' ', $v);
	return mb_substr($v, 0, $max);
};
/** Texte long : conserve les sauts de ligne, retire les balises. */
$cmp_text = function ($v, $max) {
	$v = str_replace("\r\n", "\n", strip_tags((string) $v));
	return mb_substr(trim($v), 0, $max);
};

/* ----- Campagnes ----- */
if ($action == "cmp_list") {
	$data["status"]    = 200;
	$data["campaigns"] = cl_cmp_campaigns();
	$data["kpis"]      = cl_cmp_kpis();
}

else if ($action == "cmp_save") {
	$id     = (int) fetch_or_get($_POST["id"], 0);
	$title  = $cmp_line(fetch_or_get($_POST["title"], ""), 190);
	$subj   = $cmp_line(fetch_or_get($_POST["subject"], ""), 190);
	$pre    = $cmp_line(fetch_or_get($_POST["preheader"], ""), 190);
	$tpl_id = (int) fetch_or_get($_POST["template_id"], 0);
	$body   = $cmp_text(fetch_or_get($_POST["body"], ""), 20000);
	$evt    = $cmp_line(fetch_or_get($_POST["event_date"], ""), 60);
	$audn   = fetch_or_get($_POST["audience"], "all");
	$mode   = fetch_or_get($_POST["mode"], "draft");
	$when   = trim((string) fetch_or_get($_POST["scheduled_at"], ""));

	if ($title === "") { $data["message"] = "Donnez un titre à la diffusion."; $data["field"] = "title"; }
	else if ($subj === "") { $data["message"] = "L'objet du message est obligatoire."; $data["field"] = "subject"; }
	else if (empty(cl_cmp_get_template($tpl_id))) { $data["message"] = "Choisissez un modèle."; $data["field"] = "template_id"; }
	else if (!isset($aud[$audn])) { $data["message"] = "Audience invalide."; $data["field"] = "audience"; }
	else {
		$ts = null; $status = "draft";
		if ($mode == "later") {
			$ts = $when !== "" ? strtotime($when) : false;
			if (!$ts || $ts < $now - 60) { $data["message"] = "Choisissez une date et une heure d'envoi à venir."; $data["field"] = "scheduled_at"; $ts = false; }
			else { $status = "scheduled"; }
		}
		if ($mode != "later" || $ts) {
			$old = $id ? cl_cmp_get_campaign($id) : array();
			if ($id && (empty($old) || in_array($old["status"], array("sending", "sent")))) {
				$data["message"] = "Cette diffusion est déjà partie : elle ne peut plus être modifiée. Dupliquez-la pour en créer une nouvelle.";
			} else {
				$row = array("title" => $title, "subject" => $subj, "preheader" => $pre, "template_id" => $tpl_id, "body" => $body, "event_date" => $evt, "audience" => $audn, "status" => $status, "scheduled_at" => $ts, "updated_at" => $now);
				if ($id) { $db->where("id", $id)->update(T_MAIL_CAMPAIGNS, $row); }
				else { $row["created_at"] = $now; $id = $db->insert(T_MAIL_CAMPAIGNS, $row); }
				$data["status"] = 200;
				$data["id"]     = (int) $id;
			}
		}
	}
}

else if ($action == "cmp_delete") {
	$id = (int) fetch_or_get($_POST["id"], 0);
	if ($id) {
		$db->where("id", $id)->delete(T_MAIL_LOG);
		$db->where("id", $id)->delete(T_MAIL_CAMPAIGNS);
		$data["status"] = 200;
	}
}

else if ($action == "cmp_duplicate") {
	$c = cl_cmp_get_campaign((int) fetch_or_get($_POST["id"], 0));
	if ($c) {
		unset($c["id"]);
		$c["title"] = mb_substr("Copie de " . $c["title"], 0, 190);
		$c["status"] = "draft"; $c["scheduled_at"] = null; $c["sent_at"] = null;
		$c["total"] = $c["sent_count"] = $c["failed_count"] = $c["open_count"] = 0;
		$c["created_at"] = $c["updated_at"] = $now;
		$data["id"]     = (int) $db->insert(T_MAIL_CAMPAIGNS, $c);
		$data["status"] = 200;
	}
}

else if ($action == "cmp_count") {
	$a = fetch_or_get($_POST["audience"], "all");
	if (isset($aud[$a])) { $data["status"] = 200; $data["count"] = cl_cmp_audience_count($a); }
}

else if ($action == "cmp_preview" || $action == "cmp_test") {
	$tpl_id = (int) fetch_or_get($_POST["template_id"], 0);
	$tpl    = cl_cmp_get_template($tpl_id);
	$camp   = array(
		"subject"    => $cmp_line(fetch_or_get($_POST["subject"], ""), 190),
		"preheader"  => $cmp_line(fetch_or_get($_POST["preheader"], ""), 190),
		"body"       => $cmp_text(fetch_or_get($_POST["body"], ""), 20000),
		"event_date" => $cmp_line(fetch_or_get($_POST["event_date"], ""), 60)
	);
	if (empty($tpl)) { $data["message"] = "Choisissez un modèle."; }
	else {
		$me  = array("username" => $cl["me"]["username"], "fname" => fetch_or_get($cl["me"]["fname"], ""));
		$msg = cl_cmp_render($tpl, $camp, $me, "");
		if ($action == "cmp_preview") {
			$data["status"] = 200; $data["html"] = $msg["html"];
		} else {
			$mr = $db->rawQueryOne("SELECT email FROM `" . T_USERS . "` WHERE id = ?", array((int) $cl["me"]["id"]));
			$to = $mr ? $mr["email"] : "";
			if ($to === "") { $data["message"] = "Votre compte n'a pas d'adresse e-mail."; }
			else if (!cl_cmp_smtp_ok()) { $data["message"] = "Le serveur d'envoi (SMTP) est injoignable : vérifiez Réglages > Paramètres SMTP."; }
			else if (cl_cmp_mail($to, $me["username"], "[TEST] " . $msg["subject"], $msg["html"])) { $data["status"] = 200; $data["to"] = $to; }
			else { $data["message"] = "L'envoi du test a échoué."; }
		}
	}
}

else if ($action == "cmp_send") {
	$id = (int) fetch_or_get($_POST["id"], 0);
	$c  = cl_cmp_get_campaign($id);
	if (empty($c)) { $data["message"] = "Diffusion introuvable."; }
	else if ($c["status"] == "sent") { $data["message"] = "Cette diffusion a déjà été envoyée."; }
	else if (!cl_cmp_smtp_ok()) { $data["message"] = "Le serveur d'envoi (SMTP) est injoignable : vérifiez Réglages > Paramètres SMTP."; }
	else if (cl_cmp_audience_count($c["audience"]) < 1) { $data["message"] = "Aucun destinataire dans cette audience."; }
	else {
		if ($c["status"] != "sending") { cl_cmp_start($id); }
		$data = array_merge($data, cl_cmp_process($id, 20));
		$data["status"] = 200;
	}
}

/* ----- Modèles ----- */
else if ($action == "cmp_tpl_list") {
	$data["status"]    = 200;
	$data["templates"] = cl_cmp_templates();
}

else if ($action == "cmp_tpl_save") {
	$id   = (int) fetch_or_get($_POST["id"], 0);
	$name = $cmp_line(fetch_or_get($_POST["name"], ""), 120);
	$subj = $cmp_line(fetch_or_get($_POST["subject"], ""), 190);
	$pre  = $cmp_line(fetch_or_get($_POST["preheader"], ""), 190);
	$body = $cmp_text(fetch_or_get($_POST["body"], ""), 20000);
	$ctaL = $cmp_line(fetch_or_get($_POST["cta_label"], ""), 60);
	$ctaU = cl_cmp_safe_url($cmp_line(fetch_or_get($_POST["cta_url"], ""), 255));
	$kind = fetch_or_get($_POST["kind"], "news");

	if ($name === "") { $data["message"] = "Donnez un nom au modèle."; $data["field"] = "name"; }
	else if (trim($body) === "") { $data["message"] = "Le corps du message est vide."; $data["field"] = "body"; }
	else if ($ctaL !== "" && $ctaU === "") { $data["message"] = "Le lien du bouton doit commencer par https://, http:// ou mailto: (ou être {{lien_forum}})."; $data["field"] = "cta_url"; }
	else {
		$row = array("name" => $name, "subject" => $subj, "preheader" => $pre, "body" => $body, "cta_label" => $ctaL, "cta_url" => $ctaU, "kind" => in_array($kind, array("news", "alert", "welcome")) ? $kind : "news", "updated_at" => $now);
		if ($id) { $db->where("id", $id)->update(T_MAIL_TEMPLATES, $row); }
		else { $row["created_at"] = $now; $id = $db->insert(T_MAIL_TEMPLATES, $row); }
		$data["status"] = 200; $data["id"] = (int) $id; $data["templates"] = cl_cmp_templates();
	}
}

else if ($action == "cmp_tpl_delete") {
	$id = (int) fetch_or_get($_POST["id"], 0);
	$used = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_MAIL_CAMPAIGNS . "` WHERE template_id = ? AND status IN ('draft','scheduled','sending')", array($id));
	if (!empty($used["n"])) { $data["message"] = "Ce modèle est utilisé par une diffusion en brouillon, programmée ou en cours."; }
	else { $db->where("id", $id)->delete(T_MAIL_TEMPLATES); $data["status"] = 200; $data["templates"] = cl_cmp_templates(); }
}

else if ($action == "cmp_tpl_preview") {
	$tpl = array(
		"subject"   => $cmp_line(fetch_or_get($_POST["subject"], ""), 190),
		"preheader" => $cmp_line(fetch_or_get($_POST["preheader"], ""), 190),
		"body"      => $cmp_text(fetch_or_get($_POST["body"], ""), 20000),
		"cta_label" => $cmp_line(fetch_or_get($_POST["cta_label"], ""), 60),
		"cta_url"   => cl_cmp_safe_url($cmp_line(fetch_or_get($_POST["cta_url"], ""), 255))
	);
	$sample = array("username" => "kodjo", "fname" => "Kodjo");
	$msg    = cl_cmp_render($tpl, array("event_date" => "samedi 20 décembre, 15 h"), $sample, "");
	$data["status"] = 200; $data["html"] = $msg["html"]; $data["subject"] = $msg["subject"];
}

else {
	$data["message"] = "Action inconnue.";
}
