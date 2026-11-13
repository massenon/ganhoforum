<?php
# @*************************************************************************@
# @ Ganho Forum : suite d'administration (modération renforcée, membres,     @
# @ rôles et badges, espaces, mise en avant, annonces, événements,           @
# @ suggestions, mots interdits, journal d'audit). Tables créées à la demande. @
# @*************************************************************************@

foreach (array("AUDIT" => "cl_adm_audit", "WORDS" => "cl_adm_words", "ROLES" => "cl_adm_roles", "BADGES" => "cl_adm_badges",
	"SANCTIONS" => "cl_adm_sanctions", "SPACES" => "cl_adm_spaces", "PINNED" => "cl_adm_pinned", "ANNOUNCES" => "cl_adm_announcements",
	"EVENTS" => "cl_adm_events", "SUGGESTIONS" => "cl_adm_suggestions") as $k => $v) {
	if (!defined("T_ADM_" . $k)) { define("T_ADM_" . $k, $v); }
}

function cl_adm_install() {
	global $db;
	static $done = false;
	if ($done) { return true; }
	$done = true;
	$opt = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_AUDIT . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `admin_id` INT UNSIGNED NOT NULL, `action` VARCHAR(40) NOT NULL, `target_type` VARCHAR(20) NOT NULL DEFAULT '', `target_id` INT UNSIGNED NOT NULL DEFAULT 0, `details` VARCHAR(500) NOT NULL DEFAULT '', `time` INT UNSIGNED NOT NULL, KEY `time` (`time`)) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_WORDS . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `word` VARCHAR(100) NOT NULL, `action` VARCHAR(8) NOT NULL DEFAULT 'block', `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_ROLES . "` (`user_id` INT UNSIGNED NOT NULL, `role` VARCHAR(20) NOT NULL, PRIMARY KEY (`user_id`,`role`)) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_BADGES . "` (`user_id` INT UNSIGNED NOT NULL, `badge` VARCHAR(30) NOT NULL, `time` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (`user_id`,`badge`)) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_SANCTIONS . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL, `type` VARCHAR(10) NOT NULL, `reason` VARCHAR(300) NOT NULL DEFAULT '', `until` INT UNSIGNED NULL, `admin_id` INT UNSIGNED NOT NULL DEFAULT 0, `time` INT UNSIGNED NOT NULL, `released` TINYINT NOT NULL DEFAULT 0, KEY `user_id` (`user_id`)) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_SPACES . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(120) NOT NULL, `description` VARCHAR(300) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, `archived` TINYINT NOT NULL DEFAULT 0, `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_PINNED . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `kind` VARCHAR(12) NOT NULL DEFAULT 'discussion', `title` VARCHAR(160) NOT NULL, `url` VARCHAR(255) NOT NULL DEFAULT '', `sort` INT NOT NULL DEFAULT 0, `active` TINYINT NOT NULL DEFAULT 1, `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_ANNOUNCES . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(120) NOT NULL, `message` VARCHAR(500) NOT NULL DEFAULT '', `level` VARCHAR(8) NOT NULL DEFAULT 'info', `starts_at` INT UNSIGNED NULL, `ends_at` INT UNSIGNED NULL, `active` TINYINT NOT NULL DEFAULT 1, `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_EVENTS . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(160) NOT NULL, `description` VARCHAR(600) NOT NULL DEFAULT '', `kind` VARCHAR(16) NOT NULL DEFAULT 'groupe', `starts_at` INT UNSIGNED NULL, `location` VARCHAR(255) NOT NULL DEFAULT '', `status` VARCHAR(10) NOT NULL DEFAULT 'planifie', `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_ADM_SUGGESTIONS . "` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `user_id` INT UNSIGNED NOT NULL DEFAULT 0, `subject` VARCHAR(160) NOT NULL, `message` VARCHAR(2000) NOT NULL DEFAULT '', `status` VARCHAR(8) NOT NULL DEFAULT 'new', `created_at` INT UNSIGNED NOT NULL DEFAULT 0, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	$db->rawQuery("CREATE TABLE IF NOT EXISTS `cl_adm_warn_seen` (`sanction_id` INT UNSIGNED NOT NULL PRIMARY KEY, `t` INT UNSIGNED NOT NULL DEFAULT 0) $opt");
	cl_adm_release_expired();
	return true;
}

function cl_adm_audit($action, $type = "", $id = 0, $details = "") {
	global $db, $cl;
	$db->insert(T_ADM_AUDIT, array("admin_id" => (int) (isset($cl["me"]["id"]) ? $cl["me"]["id"] : 0), "action" => mb_substr($action, 0, 40), "target_type" => $type, "target_id" => (int) $id, "details" => mb_substr((string) $details, 0, 500), "time" => time()));
}

/** Réactive les comptes dont la suspension est terminée. */
function cl_adm_release_expired() {
	global $db;
	$rows = $db->rawQuery("SELECT id, user_id FROM `" . T_ADM_SANCTIONS . "` WHERE type = 'suspend' AND released = 0 AND `until` IS NOT NULL AND `until` < ?", array(time()));
	foreach ($rows as $r) {
		$db->rawQuery("UPDATE `" . T_ADM_SANCTIONS . "` SET released = 1 WHERE id = ?", array((int) $r["id"]));
		$open = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_ADM_SANCTIONS . "` WHERE user_id = ? AND released = 0 AND type IN ('suspend','ban')", array((int) $r["user_id"]));
		if (empty($open["n"])) { $db->rawQuery("UPDATE `" . T_USERS . "` SET active = '1' WHERE id = ? AND active = '0'", array((int) $r["user_id"])); }
	}
}

/* --------------------------------------------------------------- Filtre de mots */

/** Renvoie le mot interdit trouvé (action « block ») ou chaîne vide. */
function cl_adm_blocked_word($text) {
	global $db;
	cl_adm_install();
	$text = mb_strtolower((string) $text);
	foreach ($db->rawQuery("SELECT word FROM `" . T_ADM_WORDS . "` WHERE action = 'block'") as $w) {
		$w = mb_strtolower($w["word"]);
		if ($w !== "" && mb_strpos($text, $w) !== false) { return $w; }
	}
	return "";
}

/* --------------------------------------------------------------- Entités CRUD génériques */

function cl_adm_entities() {
	return array(
		"words" => array("table" => T_ADM_WORDS, "title" => "Mots interdits et surveillés", "singular" => "un mot", "order" => "id DESC", "audit" => true,
			"help" => "« Bloquer » empêche la publication d’un message qui contient ce mot (le membre est prévenu). « Surveiller » fait remonter les publications concernées dans la file de modération.",
			"columns" => array(array("word", "Mot ou expression"), array("action", "Action", "enum")),
			"fields" => array(array("word", "Mot ou expression", "text", 100, true), array("action", "Action", "select", array("block" => "Bloquer la publication", "flag" => "Surveiller (file de modération)")))),
		"spaces" => array("table" => T_ADM_SPACES, "title" => "Catégories et espaces de discussion", "singular" => "un espace", "order" => "archived ASC, sort ASC, id ASC", "audit" => true,
			"help" => "Organisez les espaces de discussion (Coming out, Santé, Droits et démarches, Vie quotidienne, Soutien psychologique…). L’ordre croissant définit l’affichage ; l’archivage masque un espace sans le supprimer.",
			"columns" => array(array("name", "Espace"), array("description", "Description"), array("sort", "Ordre"), array("archived", "État", "enum")),
			"fields" => array(array("name", "Nom de l’espace", "text", 120, true), array("description", "Description", "textarea", 300), array("sort", "Ordre d’affichage", "number"), array("archived", "État", "select", array("0" => "Actif", "1" => "Archivé")))),
		"pinned" => array("table" => T_ADM_PINNED, "title" => "Mise en avant", "singular" => "un élément", "order" => "active DESC, sort ASC, id DESC", "audit" => true,
			"help" => "Les éléments actifs apparaissent dans le bloc « À la une » de la colonne de droite du forum : discussions importantes, guides pratiques, ressources d’urgence.",
			"columns" => array(array("kind", "Type", "enum"), array("title", "Titre"), array("url", "Lien"), array("sort", "Ordre"), array("active", "Statut", "enum")),
			"fields" => array(array("kind", "Type", "select", array("discussion" => "Discussion", "guide" => "Guide pratique", "urgence" => "Ressource d’urgence")), array("title", "Titre", "text", 160, true), array("url", "Lien (https://… ou /chemin)", "text", 255, true), array("sort", "Ordre d’affichage", "number"), array("active", "Statut", "select", array("1" => "Visible", "0" => "Masqué")))),
		"announcements" => array("table" => T_ADM_ANNOUNCES, "title" => "Annonces globales", "singular" => "une annonce", "order" => "active DESC, id DESC", "audit" => true,
			"help" => "Une annonce active s’affiche en bannière en haut de toutes les pages du forum, entre sa date de début et sa date de fin (facultatives).",
			"columns" => array(array("title", "Titre"), array("level", "Niveau", "enum"), array("starts_at", "Début", "date"), array("ends_at", "Fin", "date"), array("active", "Statut", "enum")),
			"fields" => array(array("title", "Titre", "text", 120, true), array("message", "Message", "textarea", 500), array("level", "Niveau", "select", array("info" => "Information", "warning" => "Attention", "urgent" => "Urgent")), array("starts_at", "Début (facultatif)", "datetime"), array("ends_at", "Fin (facultatif)", "datetime"), array("active", "Statut", "select", array("1" => "Activée", "0" => "Désactivée")))),
		"events" => array("table" => T_ADM_EVENTS, "title" => "Événements et groupes de parole", "singular" => "un événement", "order" => "starts_at ASC, id DESC", "audit" => true,
			"help" => "Planifiez les groupes de parole en ligne, ateliers et rencontres. Le calendrier est visible ici ; l’affichage public viendra dans une prochaine étape.",
			"columns" => array(array("title", "Événement"), array("kind", "Type", "enum"), array("starts_at", "Date", "date"), array("location", "Lieu ou lien"), array("status", "Statut", "enum")),
			"fields" => array(array("title", "Titre", "text", 160, true), array("description", "Description", "textarea", 600), array("kind", "Type", "select", array("groupe" => "Groupe de parole", "atelier" => "Atelier", "rencontre" => "Rencontre")), array("starts_at", "Date et heure", "datetime", 0, true), array("location", "Lieu ou lien de visio", "text", 255), array("status", "Statut", "select", array("planifie" => "Planifié", "annule" => "Annulé", "termine" => "Terminé")))),
		"suggestions" => array("table" => T_ADM_SUGGESTIONS, "title" => "Boîte à suggestions", "singular" => "une suggestion", "order" => "status = 'new' DESC, id DESC", "audit" => true, "readonly_fields" => true, "no_create" => true,
			"help" => "Idées et retours envoyés par les membres depuis la page « Boîte à suggestions » du forum (lien en pied de page).",
			"columns" => array(array("subject", "Sujet"), array("message", "Message"), array("created_at", "Reçue le", "date"), array("status", "Statut", "enum")),
			"fields" => array(array("status", "Statut", "select", array("new" => "Nouvelle", "read" => "Lue", "done" => "Traitée")))),
		"audit" => array("table" => T_ADM_AUDIT, "title" => "Journal d’audit", "singular" => "une action", "order" => "id DESC", "readonly" => true,
			"help" => "Historique des actions d’administration et de modération (qui, quoi, quand). Les entrées ne peuvent pas être modifiées.",
			"columns" => array(array("time", "Date", "date"), array("admin_name", "Par"), array("action", "Action"), array("target", "Cible"), array("details", "Détails")), "fields" => array())
	);
}

function cl_adm_enum_labels($entity, $key) {
	$e = cl_adm_entities();
	if (!isset($e[$entity])) { return array(); }
	foreach ($e[$entity]["fields"] as $f) { if ($f[0] == $key && isset($f[3]) && is_array($f[3])) { return $f[3]; } }
	return array();
}

function cl_adm_row_out($entity, $row) {
	global $db;
	$e = cl_adm_entities();
	$def = $e[$entity];
	foreach ($def["columns"] as $c) {
		$k = $c[0];
		if (!isset($row[$k])) { continue; }
		if (isset($c[2]) && $c[2] == "date") { $row[$k . "_fr"] = $row[$k] ? date("d/m/Y H:i", (int) $row[$k]) : "—"; $row[$k . "_input"] = $row[$k] ? date("Y-m-d\TH:i", (int) $row[$k]) : ""; }
		if (isset($c[2]) && $c[2] == "enum") { $lab = cl_adm_enum_labels($entity, $k); $row[$k . "_label"] = isset($lab[$row[$k]]) ? $lab[$row[$k]] : $row[$k]; }
	}
	foreach ($def["fields"] as $f) {
		if ($f[2] == "datetime" && isset($row[$f[0]])) { $row[$f[0] . "_input"] = $row[$f[0]] ? date("Y-m-d\TH:i", (int) $row[$f[0]]) : ""; }
	}
	return $row;
}

function cl_adm_list($entity) {
	global $db;
	$e = cl_adm_entities();
	if (!isset($e[$entity])) { return array(); }
	$def = $e[$entity];
	if ($entity == "audit") {
		$rows = $db->rawQuery("SELECT a.*, COALESCE(u.username, '—') AS admin_name FROM `" . T_ADM_AUDIT . "` a LEFT JOIN `" . T_USERS . "` u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT 300");
		foreach ($rows as &$r) { $r["target"] = $r["target_type"] ? ($r["target_type"] . " #" . $r["target_id"]) : "—"; $r["time_fr"] = date("d/m/Y H:i", (int) $r["time"]); }
		return $rows;
	}
	$rows = $db->rawQuery("SELECT * FROM `" . $def["table"] . "` ORDER BY " . $def["order"] . " LIMIT 500");
	foreach ($rows as &$r) { $r = cl_adm_row_out($entity, $r); $r["id"] = (int) $r["id"]; }
	return $rows;
}

function cl_adm_safe_url($u) {
	$u = trim((string) $u);
	return ($u !== "" && preg_match('~^(https?://|/[^/])~i', $u)) ? $u : "";
}

/** Valide et enregistre une ligne. Renvoie array(id, message, field). */
function cl_adm_save($entity, $post) {
	global $db;
	$e = cl_adm_entities();
	if (!isset($e[$entity]) || !empty($e[$entity]["readonly"])) { return array(0, "Entité inconnue.", ""); }
	$def = $e[$entity];
	$id  = (int) (isset($post["id"]) ? $post["id"] : 0);
	$row = array();
	foreach ($def["fields"] as $f) {
		$k = $f[0]; $type = $f[2]; $max = isset($f[3]) && is_int($f[3]) ? $f[3] : 0; $req = !empty($f[4]);
		$v = isset($post[$k]) ? $post[$k] : "";
		if (is_array($v)) { $v = ""; }
		if ($type == "text") { $v = preg_replace('/\s+/', ' ', trim(strip_tags($v))); if ($max) { $v = mb_substr($v, 0, $max); } }
		else if ($type == "textarea") { $v = trim(strip_tags(str_replace("\r\n", "\n", $v))); if ($max) { $v = mb_substr($v, 0, $max); } }
		else if ($type == "number") { $v = (int) $v; }
		else if ($type == "select") { $opts = $f[3]; if (!isset($opts[(string) $v])) { return array(0, "Valeur invalide pour « " . $f[1] . " ».", $k); } }
		else if ($type == "datetime") { $v = trim($v) === "" ? null : strtotime($v); if ($v === false) { $v = null; } }
		if ($req && ($v === "" || $v === null)) { return array(0, "Le champ « " . $f[1] . " » est obligatoire.", $k); }
		if ($k == "url") { $u = cl_adm_safe_url($v); if ($u === "") { return array(0, "Le lien doit commencer par https://, http:// ou /.", $k); } $v = $u; }
		$row[$k] = $v;
	}
	if ($entity == "announcements" && $row["starts_at"] && $row["ends_at"] && $row["ends_at"] < $row["starts_at"]) { return array(0, "La fin doit être après le début.", "ends_at"); }
	$now = time();
	if ($entity == "suggestions") { $row = array("status" => $row["status"]); }
	if (isset($def["fields"]) && $entity != "suggestions") { $row["updated_at"] = $now; }
	if ($entity == "suggestions") { $row["updated_at"] = $now; }
	if ($id) {
		$db->where("id", $id)->update($def["table"], $row);
		cl_adm_audit("Modification", $entity, $id, isset($row["title"]) ? $row["title"] : (isset($row["name"]) ? $row["name"] : (isset($row["word"]) ? $row["word"] : "")));
	} else {
		if (!empty($def["no_create"])) { return array(0, "Création impossible ici.", ""); }
		$row["created_at"] = $now;
		$id = (int) $db->insert($def["table"], $row);
		cl_adm_audit("Création", $entity, $id, isset($row["title"]) ? $row["title"] : (isset($row["name"]) ? $row["name"] : (isset($row["word"]) ? $row["word"] : "")));
	}
	return array($id, "", "");
}

/* --------------------------------------------------------------- Indicateurs */

function cl_adm_kpis() {
	global $db;
	$one = function ($sql, $p = array()) use ($db) { $r = $p ? $db->rawQueryOne($sql, $p) : $db->rawQueryOne($sql); return $r ? (int) array_values($r)[0] : 0; };
	$today = strtotime("today"); $d7 = time() - 7 * 86400; $d30 = time() - 30 * 86400;
	$users = $one("SELECT COUNT(*) FROM `" . T_USERS . "`");
	$act30 = $one("SELECT COUNT(*) FROM `" . T_USERS . "` WHERE CAST(last_active AS UNSIGNED) > ?", array($d30));
	$pubs7 = $one("SELECT COUNT(*) FROM `" . T_PUBS . "` WHERE target = 'publication' AND status = 'active' AND CAST(time AS UNSIGNED) > ?", array($d7));
	$reps7 = $one("SELECT COUNT(*) FROM `" . T_PUBS . "` WHERE target = 'pub_reply' AND status = 'active' AND CAST(time AS UNSIGNED) > ?", array($d7));
	$msgs7 = $one("SELECT COUNT(*) FROM `" . T_MSGS . "` WHERE sent_by = owner AND CAST(time AS UNSIGNED) > ?", array($d7));
	$rp = $one("SELECT COUNT(*) FROM `" . T_PROF_REPORTS . "` WHERE seen = '0'");
	$rb = $one("SELECT COUNT(*) FROM `" . T_PUB_REPORTS . "` WHERE seen = '0'");
	$rt = $one("SELECT (SELECT COUNT(*) FROM `" . T_PROF_REPORTS . "`) + (SELECT COUNT(*) FROM `" . T_PUB_REPORTS . "`)");
	$p30 = $one("SELECT COUNT(*) FROM `" . T_PUBS . "` WHERE target = 'publication' AND status = 'active' AND CAST(time AS UNSIGNED) > ?", array($d30));
	$p30r = $one("SELECT COUNT(*) FROM `" . T_PUBS . "` WHERE target = 'publication' AND status = 'active' AND replys_count > 0 AND CAST(time AS UNSIGNED) > ?", array($d30));
	return array(
		"users" => $users,
		"new_today" => $one("SELECT COUNT(*) FROM `" . T_USERS . "` WHERE CAST(joined AS UNSIGNED) >= ?", array($today)),
		"retention" => $users ? round(100 * $act30 / $users) : 0, "active30" => $act30,
		"pubs7" => $pubs7, "replies7" => $reps7, "messages7" => $msgs7,
		"pending" => $rp + $rb, "pending_profiles" => $rp, "pending_posts" => $rb,
		"handled_rate" => $rt ? round(100 * ($rt - $rp - $rb) / $rt) : null, "reports_total" => $rt,
		"answered_rate" => $p30 ? round(100 * $p30r / $p30) : null, "pubs30" => $p30
	);
}

function cl_adm_pending_reports() {
	global $db;
	$a = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_PROF_REPORTS . "` WHERE seen = '0'");
	$b = $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_PUB_REPORTS . "` WHERE seen = '0'");
	return (int) $a["n"] + (int) $b["n"];
}

/* --------------------------------------------------------------- File de modération */

function cl_adm_queue($show = "pending") {
	global $db;
	$seen = $show == "done" ? "1" : "0";
	$out = array();
	$pubs = $db->rawQuery("SELECT r.id, r.post_id, r.reason, r.comment, r.time, r.seen, p.text, p.status AS pub_status, p.user_id AS author_id, au.username AS author, ru.username AS reporter,
		(SELECT COUNT(*) FROM `" . T_PUB_REPORTS . "` x WHERE x.post_id = r.post_id) AS same_count
		FROM `" . T_PUB_REPORTS . "` r LEFT JOIN `" . T_PUBS . "` p ON p.id = r.post_id LEFT JOIN `" . T_USERS . "` au ON au.id = p.user_id LEFT JOIN `" . T_USERS . "` ru ON ru.id = r.user_id
		WHERE r.seen = ? ORDER BY r.id DESC LIMIT 200", array($seen));
	foreach ($pubs as $r) {
		$n = (int) $r["same_count"];
		$out[] = array("key" => "pub-" . $r["id"], "kind" => "pub", "id" => (int) $r["id"], "target_id" => (int) $r["post_id"], "user_id" => (int) $r["author_id"],
			"who" => $r["author"] ?: "(supprimé)", "reporter" => $r["reporter"] ?: "(filtre automatique)", "preview" => mb_substr(trim(strip_tags((string) $r["text"])), 0, 220), "comment" => mb_substr((string) $r["comment"], 0, 220),
			"hidden" => $r["pub_status"] != "active", "count" => $n, "urgency" => $n >= 3 ? "high" : ($n == 2 ? "mid" : "low"), "time" => (int) $r["time"], "time_fr" => $r["time"] ? date("d/m/Y H:i", (int) $r["time"]) : "—",
			"url" => cl_link("thread/" . (int) $r["post_id"]));
	}
	$profs = $db->rawQuery("SELECT r.id, r.profile_id, r.reason, r.comment, r.time, pu.username AS profile_name, ru.username AS reporter,
		(SELECT COUNT(*) FROM `" . T_PROF_REPORTS . "` x WHERE x.profile_id = r.profile_id) AS same_count
		FROM `" . T_PROF_REPORTS . "` r LEFT JOIN `" . T_USERS . "` pu ON pu.id = r.profile_id LEFT JOIN `" . T_USERS . "` ru ON ru.id = r.user_id WHERE r.seen = ? ORDER BY r.id DESC LIMIT 200", array($seen));
	foreach ($profs as $r) {
		$n = (int) $r["same_count"];
		$out[] = array("key" => "prof-" . $r["id"], "kind" => "prof", "id" => (int) $r["id"], "target_id" => (int) $r["profile_id"], "user_id" => (int) $r["profile_id"],
			"who" => $r["profile_name"] ?: "(supprimé)", "reporter" => $r["reporter"] ?: "—", "preview" => "Profil signalé : @" . ($r["profile_name"] ?: "?"), "comment" => mb_substr((string) $r["comment"], 0, 220),
			"hidden" => false, "count" => $n, "urgency" => $n >= 3 ? "high" : ($n == 2 ? "mid" : "low"), "time" => (int) $r["time"], "time_fr" => $r["time"] ? date("d/m/Y H:i", (int) $r["time"]) : "—",
			"url" => $r["profile_name"] ? cl_link($r["profile_name"]) : "");
	}
	usort($out, function ($a, $b) { $o = array("high" => 0, "mid" => 1, "low" => 2); return $o[$a["urgency"]] <=> $o[$b["urgency"]] ?: $b["time"] <=> $a["time"]; });
	return $out;
}

/** Publications récentes contenant un mot « surveillé ». */
function cl_adm_flagged() {
	global $db;
	$out = array();
	foreach ($db->rawQuery("SELECT word FROM `" . T_ADM_WORDS . "` WHERE action = 'flag'") as $w) {
		$like = "%" . str_replace(array("%", "_"), array("\\%", "\\_"), $w["word"]) . "%";
		foreach ($db->rawQuery("SELECT p.id, p.text, p.time, u.username FROM `" . T_PUBS . "` p LEFT JOIN `" . T_USERS . "` u ON u.id = p.user_id WHERE p.status = 'active' AND p.text LIKE ? ORDER BY p.id DESC LIMIT 20", array($like)) as $p) {
			$out[$p["id"]] = array("id" => (int) $p["id"], "word" => $w["word"], "who" => $p["username"], "preview" => mb_substr(trim(strip_tags((string) $p["text"])), 0, 200), "time_fr" => date("d/m/Y H:i", (int) $p["time"]), "url" => cl_link("thread/" . (int) $p["id"]));
		}
	}
	return array_values($out);
}

/** Sanction ou action sur un compte. */
function cl_adm_sanction($user_id, $type, $days = 0, $reason = "") {
	global $db, $cl;
	$user_id = (int) $user_id;
	$u = $db->rawQueryOne("SELECT id, username, admin FROM `" . T_USERS . "` WHERE id = ?", array($user_id));
	if (!$u) { return "Compte introuvable."; }
	if ($u["admin"] == "1" || $user_id == (int) $cl["me"]["id"]) { return "Un compte administrateur ne peut pas être sanctionné ici."; }
	$now = time(); $reason = mb_substr(trim(strip_tags((string) $reason)), 0, 300);
	if ($type == "warn") {
		$db->insert(T_ADM_SANCTIONS, array("user_id" => $user_id, "type" => "warn", "reason" => $reason, "admin_id" => (int) $cl["me"]["id"], "time" => $now, "released" => 1));
		cl_adm_audit("Avertissement", "user", $user_id, $u["username"] . ($reason ? " : " . $reason : ""));
	} else if ($type == "suspend" || $type == "ban") {
		$until = $type == "suspend" ? $now + max(1, min(365, (int) $days)) * 86400 : null;
		$db->insert(T_ADM_SANCTIONS, array("user_id" => $user_id, "type" => $type, "reason" => $reason, "until" => $until, "admin_id" => (int) $cl["me"]["id"], "time" => $now));
		$db->rawQuery("UPDATE `" . T_USERS . "` SET active = '0' WHERE id = ?", array($user_id));
		cl_adm_audit($type == "ban" ? "Bannissement" : "Suspension", "user", $user_id, $u["username"] . ($type == "suspend" ? " (" . (int) $days . " j)" : "") . ($reason ? " : " . $reason : ""));
	} else if ($type == "restore") {
		$db->rawQuery("UPDATE `" . T_ADM_SANCTIONS . "` SET released = 1 WHERE user_id = ? AND type IN ('suspend','ban')", array($user_id));
		$db->rawQuery("UPDATE `" . T_USERS . "` SET active = '1' WHERE id = ? AND active = '0'", array($user_id));
		cl_adm_audit("Réactivation", "user", $user_id, $u["username"]);
	} else { return "Action inconnue."; }
	return "";
}

/* --------------------------------------------------------------- Membres */

function cl_adm_roles_labels() { return array("moderator" => "Modérateur·rice", "verified" => "Membre vérifié", "volunteer" => "Bénévole écoutant·e"); }
function cl_adm_badge_labels() { return array("conseiller" => "Conseiller bienveillant", "actif" => "Membre actif", "pilier" => "Pilier de la communauté"); }

function cl_adm_members($f) {
	global $db;
	cl_adm_release_expired();
	$w = array("1=1"); $p = array();
	$q = trim((string) (isset($f["q"]) ? $f["q"] : ""));
	if ($q !== "") { $w[] = "(u.username LIKE ? OR u.email LIKE ? OR u.fname LIKE ?)"; $like = "%" . str_replace(array("%", "_"), array("\\%", "\\_"), $q) . "%"; array_push($p, $like, $like, $like); }
	$status = isset($f["status"]) ? $f["status"] : "";
	if ($status == "active") { $w[] = "u.active = '1'"; }
	else if ($status == "suspended") { $w[] = "u.active = '0' AND EXISTS (SELECT 1 FROM `" . T_ADM_SANCTIONS . "` s WHERE s.user_id = u.id AND s.type = 'suspend' AND s.released = 0)"; }
	else if ($status == "banned") { $w[] = "u.active = '0' AND EXISTS (SELECT 1 FROM `" . T_ADM_SANCTIONS . "` s WHERE s.user_id = u.id AND s.type = 'ban' AND s.released = 0)"; }
	$role = isset($f["role"]) ? $f["role"] : "";
	if ($role == "admin") { $w[] = "u.admin = '1'"; }
	else if (isset(cl_adm_roles_labels()[$role])) { $w[] = "EXISTS (SELECT 1 FROM `" . T_ADM_ROLES . "` r WHERE r.user_id = u.id AND r.role = ?)"; $p[] = $role; }
	$act = isset($f["activity"]) ? $f["activity"] : "";
	if ($act == "active30") { $w[] = "CAST(u.last_active AS UNSIGNED) > " . (time() - 30 * 86400); }
	else if ($act == "inactive90") { $w[] = "CAST(u.last_active AS UNSIGNED) < " . (time() - 90 * 86400); }
	if (!empty($f["from"]) && ($t = strtotime($f["from"]))) { $w[] = "CAST(u.joined AS UNSIGNED) >= " . (int) $t; }
	if (!empty($f["to"]) && ($t = strtotime($f["to"] . " 23:59:59"))) { $w[] = "CAST(u.joined AS UNSIGNED) <= " . (int) $t; }
	$page = max(1, (int) (isset($f["page"]) ? $f["page"] : 1)); $per = 25;
	$cnt = $p ? $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_USERS . "` u WHERE " . implode(" AND ", $w), $p) : $db->rawQueryOne("SELECT COUNT(*) AS n FROM `" . T_USERS . "` u WHERE " . implode(" AND ", $w));
	$sqlm = "SELECT u.id, u.username, u.fname, u.lname, u.email, u.joined, u.last_active, u.active, u.admin, u.verified, u.posts FROM `" . T_USERS . "` u WHERE " . implode(" AND ", $w) . " ORDER BY u.id DESC LIMIT " . (($page - 1) * $per) . ", " . $per;
	$rows = $p ? $db->rawQuery($sqlm, $p) : $db->rawQuery($sqlm);
	$ids = array_map(function ($r) { return (int) $r["id"]; }, $rows);
	$roles = array(); $badges = array(); $sanc = array(); $warn = array();
	if ($ids) {
		$in = implode(",", $ids);
		foreach ($db->rawQuery("SELECT user_id, role FROM `" . T_ADM_ROLES . "` WHERE user_id IN ($in)") as $r) { $roles[$r["user_id"]][] = $r["role"]; }
		foreach ($db->rawQuery("SELECT user_id, badge FROM `" . T_ADM_BADGES . "` WHERE user_id IN ($in)") as $r) { $badges[$r["user_id"]][] = $r["badge"]; }
		foreach ($db->rawQuery("SELECT user_id, type, `until` FROM `" . T_ADM_SANCTIONS . "` WHERE user_id IN ($in) AND released = 0 AND type IN ('suspend','ban')") as $r) { $sanc[$r["user_id"]] = $r; }
		foreach ($db->rawQuery("SELECT user_id, COUNT(*) AS n FROM `" . T_ADM_SANCTIONS . "` WHERE user_id IN ($in) AND type = 'warn' GROUP BY user_id") as $r) { $warn[$r["user_id"]] = (int) $r["n"]; }
	}
	$out = array();
	foreach ($rows as $r) {
		$id = (int) $r["id"];
		$st = $r["active"] == "1" ? "active" : (isset($sanc[$id]) ? ($sanc[$id]["type"] == "ban" ? "banned" : "suspended") : "inactive");
		$out[] = array("id" => $id, "username" => $r["username"], "name" => trim($r["fname"] . " " . $r["lname"]), "email" => $r["email"], "joined_fr" => $r["joined"] ? date("d/m/Y", (int) $r["joined"]) : "—",
			"last_fr" => $r["last_active"] ? date("d/m/Y", (int) $r["last_active"]) : "—", "status" => $st, "until_fr" => isset($sanc[$id]) && $sanc[$id]["until"] ? date("d/m/Y", (int) $sanc[$id]["until"]) : "",
			"is_admin" => $r["admin"] == "1", "verified" => $r["verified"] == "1", "roles" => isset($roles[$id]) ? $roles[$id] : array(), "badges" => isset($badges[$id]) ? $badges[$id] : array(), "warnings" => isset($warn[$id]) ? $warn[$id] : 0, "posts" => (int) $r["posts"], "url" => cl_link($r["username"]));
	}
	return array("members" => $out, "total" => (int) $cnt["n"], "page" => $page, "pages" => max(1, (int) ceil($cnt["n"] / $per)));
}

/* --------------------------------------------------------------- Habillage forum */

/** Annonce globale en cours (ou tableau vide). */
function cl_adm_current_announcement() {
	global $db;
	try {
		cl_adm_install();
		$now = time();
		$r = $db->rawQueryOne("SELECT id, title, message, level FROM `" . T_ADM_ANNOUNCES . "` WHERE active = 1 AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?) ORDER BY id DESC LIMIT 1", array($now, $now));
		return $r ? $r : array();
	} catch (Exception $e) { return array(); }
}

function cl_adm_pinned_items() {
	global $db;
	try {
		cl_adm_install();
		return $db->rawQuery("SELECT kind, title, url FROM `" . T_ADM_PINNED . "` WHERE active = 1 ORDER BY sort ASC, id DESC LIMIT 8");
	} catch (Exception $e) { return array(); }
}


/** Vrai si le compte connecté a le rôle « modérateur·rice » (sans être administrateur). */
function cl_adm_is_moderator() {
	global $db, $cl;
	if (empty($cl['is_logged']) || empty($cl['me']['id'])) { return false; }
	try {
		$r = $db->rawQueryOne("SELECT 1 AS ok FROM `" . T_ADM_ROLES . "` WHERE user_id = ? AND role = 'moderator'", array((int) $cl['me']['id']));
	} catch (\Throwable $e) { return false; }
	return !empty($r);
}

/** Avertissements de la modération que le membre n'a pas encore lus (30 derniers jours, 3 au plus). */
function cl_adm_unseen_warnings($user_id) {
	global $db;
	try {
		return $db->rawQuery("SELECT s.id, s.reason, s.time FROM `" . T_ADM_SANCTIONS . "` s LEFT JOIN `cl_adm_warn_seen` w ON w.sanction_id = s.id WHERE s.user_id = ? AND s.type = 'warn' AND w.sanction_id IS NULL AND s.time > ? ORDER BY s.id DESC LIMIT 3", array((int) $user_id, time() - 30 * 86400));
	} catch (\Throwable $e) { return array(); }
}
