<?php
# @*************************************************************************@
# @ Ganho Forum : actions AJAX de la suite d'administration (adm_*).         @
# @ Appelé depuis apps/native/ajax/cpanel/content.php ; admin uniquement.    @
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();

$data["status"] = 400;
// Garde : un·e modérateur·rice (non administrateur) n'a accès qu'à la file des signalements.
if (empty($cl['is_admin']) && !in_array($action, array('adm_queue', 'adm_queue_act', 'adm_flag_hide'), true)) {
	$data["message"] = "Réservé aux administrateurs.";
	return;
}
$ent = isset($_POST["entity"]) ? preg_replace('/[^a-z_]/', '', $_POST["entity"]) : "";

if ($action == "adm_list") {
	$e = cl_adm_entities();
	if (isset($e[$ent])) { $data["status"] = 200; $data["rows"] = cl_adm_list($ent); }
}

else if ($action == "adm_save") {
	list($id, $msg, $field) = cl_adm_save($ent, $_POST);
	if ($id) { $data["status"] = 200; $data["id"] = $id; $data["rows"] = cl_adm_list($ent); }
	else { $data["message"] = $msg; $data["field"] = $field; }
}

else if ($action == "adm_delete") {
	$e = cl_adm_entities();
	$id = (int) fetch_or_get($_POST["id"], 0);
	if (isset($e[$ent]) && empty($e[$ent]["readonly"]) && $id) {
		$db->where("id", $id)->delete($e[$ent]["table"]);
		cl_adm_audit("Suppression", $ent, $id, "");
		$data["status"] = 200; $data["rows"] = cl_adm_list($ent);
	}
}

else if ($action == "adm_queue") {
	$show = fetch_or_get($_POST["show"], "pending") == "done" ? "done" : "pending";
	$data["status"] = 200; $data["items"] = cl_adm_queue($show); $data["flagged"] = $show == "pending" ? cl_adm_flagged() : array();
	$data["pending"] = cl_adm_pending_reports();
}

else if ($action == "adm_queue_act") {
	if (empty($cl['is_admin']) && fetch_or_get($_POST["act"], "") === "ban") { $data["message"] = "Le bannissement est réservé aux administrateurs."; return; }
	$kind = fetch_or_get($_POST["kind"], ""); $rid = (int) fetch_or_get($_POST["id"], 0);
	$act  = fetch_or_get($_POST["act"], ""); $reason = fetch_or_get($_POST["reason"], ""); $days = (int) fetch_or_get($_POST["days"], 7);
	$table = $kind == "pub" ? T_PUB_REPORTS : ($kind == "prof" ? T_PROF_REPORTS : "");
	$rep = $table ? $db->rawQueryOne("SELECT * FROM `$table` WHERE id = ?", array($rid)) : null;
	if (!$rep) { $data["message"] = "Signalement introuvable."; }
	else {
		$pub_id = $kind == "pub" ? (int) $rep["post_id"] : 0;
		$author = $kind == "pub" ? (int) $db->rawQueryOne("SELECT user_id AS u FROM `" . T_PUBS . "` WHERE id = ?", array($pub_id))["u"] : (int) $rep["profile_id"];
		$err = "";
		if ($act == "approve") { cl_adm_audit("Signalement classé", $kind, $rid, $reason); }
		else if ($act == "hide" && $pub_id) { $db->rawQuery("UPDATE `" . T_PUBS . "` SET status = 'inactive' WHERE id = ?", array($pub_id)); cl_adm_audit("Publication masquée", "pub", $pub_id, $reason); }
		else if ($act == "show" && $pub_id) { $db->rawQuery("UPDATE `" . T_PUBS . "` SET status = 'active' WHERE id = ?", array($pub_id)); cl_adm_audit("Publication rétablie", "pub", $pub_id, ""); }
		else if (in_array($act, array("warn", "suspend", "ban")) && $author) { $err = cl_adm_sanction($author, $act, $days, $reason); }
		else { $err = "Action indisponible pour ce signalement."; }
		if ($err === "") {
			if ($act != "show") { $db->rawQuery("UPDATE `$table` SET seen = '1' WHERE id = ?", array($rid)); if ($kind == "pub") { $db->rawQuery("UPDATE `$table` SET seen = '1' WHERE post_id = ?", array($pub_id)); } }
			$data["status"] = 200;
		} else { $data["message"] = $err; }
	}
}

else if ($action == "adm_flag_hide") {
	$pid = (int) fetch_or_get($_POST["id"], 0);
	if ($pid) { $db->rawQuery("UPDATE `" . T_PUBS . "` SET status = 'inactive' WHERE id = ?", array($pid)); cl_adm_audit("Publication masquée (mot surveillé)", "pub", $pid, ""); $data["status"] = 200; }
}

else if ($action == "adm_members") {
	$data = array_merge($data, cl_adm_members($_POST)); $data["status"] = 200;
	$data["role_labels"] = cl_adm_roles_labels(); $data["badge_labels"] = cl_adm_badge_labels();
}

else if ($action == "adm_mem_role") {
	$uid = (int) fetch_or_get($_POST["user_id"], 0); $role = fetch_or_get($_POST["role"], ""); $on = fetch_or_get($_POST["on"], "0") == "1";
	if ($uid && isset(cl_adm_roles_labels()[$role])) {
		if ($on) { $db->rawQuery("INSERT IGNORE INTO `" . T_ADM_ROLES . "` (user_id, role) VALUES (?, ?)", array($uid, $role)); }
		else { $db->rawQuery("DELETE FROM `" . T_ADM_ROLES . "` WHERE user_id = ? AND role = ?", array($uid, $role)); }
		if ($role == "verified") { $db->rawQuery("UPDATE `" . T_USERS . "` SET verified = ? WHERE id = ?", array($on ? "1" : "0", $uid)); }
		cl_adm_audit($on ? "Rôle attribué" : "Rôle retiré", "user", $uid, cl_adm_roles_labels()[$role]);
		$data["status"] = 200;
	}
}

else if ($action == "adm_mem_badge") {
	$uid = (int) fetch_or_get($_POST["user_id"], 0); $b = fetch_or_get($_POST["badge"], ""); $on = fetch_or_get($_POST["on"], "0") == "1";
	if ($uid && isset(cl_adm_badge_labels()[$b])) {
		if ($on) { $db->rawQuery("INSERT IGNORE INTO `" . T_ADM_BADGES . "` (user_id, badge, time) VALUES (?, ?, ?)", array($uid, $b, time())); }
		else { $db->rawQuery("DELETE FROM `" . T_ADM_BADGES . "` WHERE user_id = ? AND badge = ?", array($uid, $b)); }
		cl_adm_audit($on ? "Badge attribué" : "Badge retiré", "user", $uid, cl_adm_badge_labels()[$b]);
		$data["status"] = 200;
	}
}

else if ($action == "adm_mem_sanction") {
	$err = cl_adm_sanction((int) fetch_or_get($_POST["user_id"], 0), fetch_or_get($_POST["type"], ""), (int) fetch_or_get($_POST["days"], 7), fetch_or_get($_POST["reason"], ""));
	if ($err === "") { $data["status"] = 200; } else { $data["message"] = $err; }
}

else if ($action == "adm_kpis") {
	$data["status"] = 200; $data["kpis"] = cl_adm_kpis();
}

else { $data["message"] = "Action inconnue."; }
