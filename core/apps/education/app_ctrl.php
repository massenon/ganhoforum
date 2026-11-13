<?php
# @*************************************************************************@
# @ Ganho Forum : contenus éducatifs (fiches) et bandes dessinées.           @
# @ Tables créées à la demande (cl_edu_install) : aucune migration à lancer. @
# @*************************************************************************@

if (!defined("T_EDU_ITEMS"))  { define("T_EDU_ITEMS", "cl_edu_items"); }
if (!defined("T_EDU_COMICS")) { define("T_EDU_COMICS", "cl_edu_comics"); }

/** Catégories proposées dans l'admin et en filtre public. */
function cl_edu_categories() {
	return array(
		"harcelement"  => "Harcèlement et intimidation",
		"intimite"     => "Images intimes et chantage",
		"surveillance" => "Surveillance et contrôle",
		"comptes"      => "Comptes, données et identité",
		"manipulation" => "Manipulation et arnaques",
		"haine"        => "Haine et attaques de groupe",
		"technologie"  => "Technologies et IA"
	);
}

function cl_edu_audiences() {
	return array("all" => "Tout public", "teens" => "Adolescent·es", "adults" => "Adultes", "pros" => "Professionnel·les et proches");
}

function cl_edu_statuses() {
	return array("draft" => "Brouillon", "published" => "Publié");
}

function cl_edu_install() {
	global $db;
	static $done = false;

	if ($done) { return true; }
	$done = true;

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_EDU_ITEMS . "` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`slug` VARCHAR(160) NOT NULL,
		`title` VARCHAR(190) NOT NULL,
		`category` VARCHAR(24) NOT NULL DEFAULT 'harcelement',
		`audience` VARCHAR(12) NOT NULL DEFAULT 'all',
		`summary` VARCHAR(400) NOT NULL DEFAULT '',
		`body` MEDIUMTEXT NOT NULL,
		`image` VARCHAR(255) NOT NULL DEFAULT '',
		`status` VARCHAR(12) NOT NULL DEFAULT 'draft',
		`pinned` TINYINT UNSIGNED NOT NULL DEFAULT 0,
		`created_at` INT UNSIGNED NOT NULL DEFAULT 0,
		`updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
		UNIQUE KEY `slug` (`slug`),
		KEY `status` (`status`,`pinned`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$db->rawQuery("CREATE TABLE IF NOT EXISTS `" . T_EDU_COMICS . "` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`slug` VARCHAR(160) NOT NULL,
		`title` VARCHAR(190) NOT NULL,
		`category` VARCHAR(24) NOT NULL DEFAULT 'harcelement',
		`summary` VARCHAR(400) NOT NULL DEFAULT '',
		`cover` VARCHAR(255) NOT NULL DEFAULT '',
		`panels` MEDIUMTEXT NOT NULL,
		`status` VARCHAR(12) NOT NULL DEFAULT 'draft',
		`pinned` TINYINT UNSIGNED NOT NULL DEFAULT 0,
		`created_at` INT UNSIGNED NOT NULL DEFAULT 0,
		`updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
		UNIQUE KEY `slug` (`slug`),
		KEY `status` (`status`,`pinned`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	return true;
}

/* ---------------------------------------------------------------- Bulles de conversation (posées sur les illustrations des cases) */

/** Code couleur : couleur => sens (un pictogramme et un libellé accompagnent toujours la couleur). */
function cl_conv_colors() {
	return array(
		"vert"   => "Calme, bienveillant",
		"jaune"  => "Prudence, incertitude",
		"orange" => "Malaise",
		"rouge"  => "Colère, danger",
		"bleu"   => "Tristesse",
		"violet" => "Peur, honte"
	);
}

/** Émotions proposées pour une bulle « ressent » : clé => array(libellé court, couleur conseillée). */
function cl_conv_emotions() {
	return array(
		"calme"     => array("Calme", "vert"),
		"confiance" => array("Confiance", "vert"),
		"prudence"  => array("Prudence", "jaune"),
		"doute"     => array("Doute", "jaune"),
		"malaise"   => array("Malaise", "orange"),
		"colere"    => array("Colère", "rouge"),
		"danger"    => array("Danger", "rouge"),
		"tristesse" => array("Tristesse", "bleu"),
		"peur"      => array("Peur", "violet"),
		"honte"     => array("Honte", "violet")
	);
}

function cl_conv_types() {
	return array("dit" => "dit", "pense" => "pense", "ressent" => "ressent");
}

/** Côté de la queue de la bulle (vers le personnage) : b = en bas, t = en haut, l = à gauche, r = à droite, + coin. */
function cl_conv_tails() {
	return array("b" => "En bas", "bl" => "En bas à gauche", "br" => "En bas à droite", "t" => "En haut", "tl" => "En haut à gauche", "tr" => "En haut à droite", "l" => "À gauche", "r" => "À droite");
}

function cl_conv_line($v, $max) {
	return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, $max);
}

/**
 * Nettoie la liste de bulles d'une case (0 à 6). $strict = true : retourne array(null, message) au premier défaut ;
 * sinon tronque et ignore ce qui est invalide (lecture de données déjà enregistrées).
 */
function cl_conv_clean_bubbles($list, $strict = false) {
	$cols = cl_conv_colors(); $types = cl_conv_types(); $tails = cl_conv_tails(); $emos = cl_conv_emotions();
	$out = array();
	if (!is_array($list)) { return array(array(), ""); }
	$list = array_values($list);
	if ($strict && count($list) > 6) { return array(null, "6 bulles au maximum par case."); }
	foreach ($list as $b) {
		if (!is_array($b)) { continue; }
		$raw = trim((string) (isset($b["text"]) ? $b["text"] : ""));
		if ($raw === "") { continue; }
		if ($strict && mb_strlen(strip_tags($raw)) > 140) { return array(null, "Une bulle dépasse 140 caractères."); }
		if (count($out) >= 6) { break; }
		$type = isset($b["type"]) && isset($types[$b["type"]]) ? $b["type"] : "dit";
		$col  = isset($b["color"]) && isset($cols[$b["color"]]) ? $b["color"] : "vert";
		$emo  = isset($b["emotion"]) && isset($emos[$b["emotion"]]) ? $b["emotion"] : "";
		if ($type == "ressent" && $emo === "") {
			foreach ($emos as $k => $e) { if ($e[1] == $col) { $emo = $k; break; } }
		}
		$x = isset($b["x"]) && is_numeric($b["x"]) ? max(5, min(95, round((float) $b["x"], 1))) : 50;
		$y = isset($b["y"]) && is_numeric($b["y"]) ? max(5, min(95, round((float) $b["y"], 1))) : 20;
		$tail = isset($b["tail"]) && isset($tails[$b["tail"]]) ? $b["tail"] : "b";
		$out[] = array("type" => $type, "text" => cl_conv_line($raw, 140), "color" => $col, "emotion" => $type == "ressent" ? $emo : "", "x" => $x, "y" => $y, "tail" => $tail, "who" => cl_conv_line(isset($b["who"]) ? $b["who"] : "", 24));
	}
	return array($out, "");
}

/** Description textuelle des bulles d'une case (lecteurs d'écran et repli sans JavaScript). */
function cl_conv_alt($panel, $num) {
	$cols = cl_conv_colors(); $emos = cl_conv_emotions();
	$bubbles = isset($panel["bubbles"]) ? $panel["bubbles"] : array();
	$parts = array();
	foreach ($bubbles as $b) {
		$who = $b["who"] !== "" ? $b["who"] : "Un personnage";
		$end = preg_match('/[.!?…]$/u', $b["text"]) ? "" : ".";
		if ($b["type"] == "dit") { $parts[] = $who . " dit : " . $b["text"] . $end; }
		else if ($b["type"] == "pense") { $parts[] = $who . " pense : " . $b["text"] . $end; }
		else { $parts[] = $who . " ressent : " . mb_strtolower(isset($emos[$b["emotion"]]) ? $emos[$b["emotion"]][0] : $cols[$b["color"]]) . " (" . $b["color"] . "). " . $b["text"] . $end; }
	}
	return "Case " . $num . ". " . implode(" ", $parts);
}

/* ---------------------------------------------------------------- Utilitaires */

function cl_edu_h($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
}

function cl_edu_slugify($text) {
	$t = strtr((string) $text, array("à"=>"a","â"=>"a","ä"=>"a","á"=>"a","ç"=>"c","é"=>"e","è"=>"e","ê"=>"e","ë"=>"e","î"=>"i","ï"=>"i","í"=>"i","ô"=>"o","ö"=>"o","ó"=>"o","ù"=>"u","û"=>"u","ü"=>"u","ú"=>"u","ÿ"=>"y","œ"=>"oe","æ"=>"ae","À"=>"a","Â"=>"a","Ä"=>"a","Ç"=>"c","É"=>"e","È"=>"e","Ê"=>"e","Ë"=>"e","Î"=>"i","Ï"=>"i","Ô"=>"o","Ö"=>"o","Ù"=>"u","Û"=>"u","Ü"=>"u","’"=>"-","'"=>"-"));
	$t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t));
	$t = trim($t, '-');
	return $t === "" ? "contenu" : substr($t, 0, 120);
}

/** Slug unique dans la table donnée. */
function cl_edu_unique_slug($table, $base, $ignore_id = 0) {
	global $db;
	$slug = $base; $i = 2;
	while (true) {
		$r = $db->rawQueryOne("SELECT id FROM `" . $table . "` WHERE slug = ? AND id <> ?", array($slug, (int) $ignore_id));
		if (!$r) { return $slug; }
		$slug = $base . "-" . $i++;
	}
}

/** URL d'image sûre : chemin relatif au site ou http(s) ; sinon vide. */
function cl_edu_safe_image($url) {
	$url = trim((string) $url);
	if ($url === "") { return ""; }
	if (preg_match('~^https?://~i', $url)) { return substr($url, 0, 255); }
	if (preg_match('~^[A-Za-z0-9_./\-]+\.(jpe?g|png|webp|gif|svg)$~i', $url) && strpos($url, "..") === false) { return ltrim(substr($url, 0, 255), "/"); }
	return "";
}

function cl_edu_image_url($path) {
	$path = (string) $path;
	if ($path === "") { return ""; }
	if (preg_match('~^https?://~i', $path)) { return $path; }
	return cl_link($path);
}

/**
 * Texte structuré -> HTML sûr.
 *   ## Titre        -> intertitre      - élément       -> liste à puces
 *   1. élément      -> liste numérotée > citation      -> citation
 *   !! texte        -> encadré « À retenir »           (ligne vide = nouveau paragraphe)
 *   **gras**        -> gras
 * Retourne aussi la table des matières des intertitres.
 */
function cl_edu_render_body($text, &$toc = null) {
	$toc = array();
	$inl = function ($s) {
		$s = cl_edu_h($s);
		$s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
		return $s;
	};
	$lines = preg_split('/\R/u', trim((string) $text));
	$html = ""; $list = ""; $para = array();
	$flush_para = function () use (&$html, &$para, $inl) {
		if ($para) { $html .= "<p>" . $inl(implode(" ", $para)) . "</p>"; $para = array(); }
	};
	$close_list = function () use (&$html, &$list) {
		if ($list) { $html .= "</" . $list . ">"; $list = ""; }
	};
	$n = 0;
	foreach ($lines as $line) {
		$l = rtrim($line);
		if (trim($l) === "") { $flush_para(); $close_list(); continue; }
		if (preg_match('/^##\s+(.+)$/u', $l, $m)) {
			$flush_para(); $close_list();
			$n++; $id = "s" . $n;
			$toc[] = array($id, $m[1]);
			$html .= '<h2 id="' . $id . '">' . $inl($m[1]) . "</h2>";
		} else if (preg_match('/^-\s+(.+)$/u', $l, $m)) {
			$flush_para();
			if ($list !== "ul") { $close_list(); $html .= "<ul>"; $list = "ul"; }
			$html .= "<li>" . $inl($m[1]) . "</li>";
		} else if (preg_match('/^\d+[.)]\s+(.+)$/u', $l, $m)) {
			$flush_para();
			if ($list !== "ol") { $close_list(); $html .= "<ol>"; $list = "ol"; }
			$html .= "<li>" . $inl($m[1]) . "</li>";
		} else if (preg_match('/^>\s?(.+)$/u', $l, $m)) {
			$flush_para(); $close_list();
			$html .= "<blockquote><p>" . $inl($m[1]) . "</p></blockquote>";
		} else if (preg_match('/^!!\s*(.+)$/u', $l, $m)) {
			$flush_para(); $close_list();
			$html .= '<aside class="gn-edu-callout"><b>À retenir</b><p>' . $inl($m[1]) . "</p></aside>";
		} else {
			$close_list();
			$para[] = trim($l);
		}
	}
	$flush_para(); $close_list();
	return $html;
}

function cl_edu_reading_minutes($text) {
	$words = str_word_count(strip_tags((string) $text), 0, 'àâäçéèêëîïôöùûüÿœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒ');
	return max(1, (int) ceil($words / 190));
}

/* ---------------------------------------------------------------- Lecture (public) */

function cl_edu_get_items($only_published = true, $category = "") {
	global $db;
	$w = array(); $p = array();
	if ($only_published) { $w[] = "status = 'published'"; }
	if ($category !== "" && isset(cl_edu_categories()[$category])) { $w[] = "category = ?"; $p[] = $category; }
	$sql = "SELECT * FROM `" . T_EDU_ITEMS . "`" . ($w ? " WHERE " . implode(" AND ", $w) : "") . " ORDER BY pinned DESC, updated_at DESC, id DESC";
	$rows = $p ? $db->rawQuery($sql, $p) : $db->rawQuery($sql);
	$cats = cl_edu_categories(); $aud = cl_edu_audiences();
	foreach ($rows as &$r) {
		$r["id"] = (int) $r["id"]; $r["pinned"] = (int) $r["pinned"];
		$r["category_label"] = isset($cats[$r["category"]]) ? $cats[$r["category"]] : $r["category"];
		$r["audience_label"] = isset($aud[$r["audience"]]) ? $aud[$r["audience"]] : $r["audience"];
		$r["image_url"]      = cl_edu_image_url($r["image"]);
		$r["minutes"]        = cl_edu_reading_minutes($r["body"]);
		$r["url"]            = cl_link("contenus-educatifs/" . $r["slug"]);
		$r["date_fr"]        = date("d/m/Y", (int) $r["updated_at"]);
	}
	return $rows;
}

function cl_edu_get_item_by_slug($slug) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_EDU_ITEMS . "` WHERE slug = ? AND status = 'published'", array((string) $slug));
	return $r ? $r : array();
}

function cl_edu_get_item($id) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_EDU_ITEMS . "` WHERE id = ?", array((int) $id));
	return $r ? $r : array();
}

function cl_edu_panels_decode($json) {
	$a = json_decode((string) $json, true);
	if (!is_array($a)) { return array(); }
	$out = array();
	foreach ($a as $p) {
		if (!is_array($p)) { continue; }
		$out[] = array(
			"image"    => cl_edu_safe_image(isset($p["image"]) ? $p["image"] : ""),
			"alt"      => mb_substr(trim(strip_tags(isset($p["alt"]) ? $p["alt"] : "")), 0, 200),
			"caption"  => mb_substr(trim(strip_tags(isset($p["caption"]) ? $p["caption"] : "")), 0, 300),
			"dialogue" => mb_substr(trim(strip_tags(isset($p["dialogue"]) ? $p["dialogue"] : "")), 0, 400),
				"bubbles"  => cl_conv_clean_bubbles(isset($p["bubbles"]) ? $p["bubbles"] : array(), false)[0]
			);
	}
	return $out;
}

function cl_edu_get_comics($only_published = true) {
	global $db;
	$sql  = "SELECT * FROM `" . T_EDU_COMICS . "`" . ($only_published ? " WHERE status = 'published'" : "") . " ORDER BY pinned DESC, updated_at DESC, id DESC";
	$rows = $db->rawQuery($sql);
	$cats = cl_edu_categories();
	foreach ($rows as &$r) {
		$r["id"] = (int) $r["id"]; $r["pinned"] = (int) $r["pinned"];
		$r["panels_list"]    = cl_edu_panels_decode($r["panels"]);
		$r["panel_count"]    = count($r["panels_list"]);
		$r["has_bubbles"]    = (bool) array_filter($r["panels_list"], function ($pp) { return !empty($pp["bubbles"]); });
		$r["category_label"] = isset($cats[$r["category"]]) ? $cats[$r["category"]] : $r["category"];
		$cover = $r["cover"] !== "" ? $r["cover"] : (($r["panels_list"] && isset($r["panels_list"][0]["image"])) ? $r["panels_list"][0]["image"] : "");
		$r["cover_url"]      = cl_edu_image_url($cover);
		$r["url"]            = cl_link("bandes-dessinees/" . $r["slug"]);
		$r["date_fr"]        = date("d/m/Y", (int) $r["updated_at"]);
	}
	return $rows;
}

function cl_edu_get_comic_by_slug($slug) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_EDU_COMICS . "` WHERE slug = ? AND status = 'published'", array((string) $slug));
	return $r ? $r : array();
}

function cl_edu_get_comic($id) {
	global $db;
	$r = $db->rawQueryOne("SELECT * FROM `" . T_EDU_COMICS . "` WHERE id = ?", array((int) $id));
	return $r ? $r : array();
}

/* ---------------------------------------------------------------- Écriture (admin) */

/** Enregistre une fiche ; retourne array(id) ou array(null, message, champ). */
function cl_edu_save_item($in) {
	global $db;
	$cats = cl_edu_categories(); $aud = cl_edu_audiences(); $sts = cl_edu_statuses();
	$id    = (int) (isset($in["id"]) ? $in["id"] : 0);
	$title = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) (isset($in["title"]) ? $in["title"] : "")))), 0, 190);
	$cat   = isset($in["category"]) ? $in["category"] : "";
	$aud_k = isset($in["audience"]) ? $in["audience"] : "all";
	$sum   = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) (isset($in["summary"]) ? $in["summary"] : "")))), 0, 400);
	$body  = mb_substr(trim(str_replace("\r\n", "\n", strip_tags((string) (isset($in["body"]) ? $in["body"] : "")))), 0, 60000);
	$img_in = isset($in["image"]) ? trim((string) $in["image"]) : "";
	$img   = cl_edu_safe_image($img_in);
	$st    = isset($in["status"]) ? $in["status"] : "draft";
	$pin   = !empty($in["pinned"]) && $in["pinned"] !== "0" && $in["pinned"] !== "false" ? 1 : 0;

	if ($title === "") { return array(null, "Donnez un titre à la fiche.", "title"); }
	if (!isset($cats[$cat])) { return array(null, "Choisissez une catégorie.", "category"); }
	if (!isset($aud[$aud_k])) { $aud_k = "all"; }
	if (!isset($sts[$st])) { $st = "draft"; }
	if ($sum === "") { return array(null, "Le résumé est obligatoire : il s'affiche dans la liste.", "summary"); }
	if (mb_strlen($body) < 40) { return array(null, "Le contenu est trop court.", "body"); }
	if ($img_in !== "" && $img === "") { return array(null, "Adresse d'image invalide (chemin du site ou https://).", "image"); }

	$now = time();
	$row = array("title" => $title, "category" => $cat, "audience" => $aud_k, "summary" => $sum, "body" => $body, "image" => $img, "status" => $st, "pinned" => $pin, "updated_at" => $now);
	if ($id) {
		if (!cl_edu_get_item($id)) { return array(null, "Fiche introuvable.", ""); }
		$db->where("id", $id)->update(T_EDU_ITEMS, $row);
	} else {
		$row["slug"]       = cl_edu_unique_slug(T_EDU_ITEMS, cl_edu_slugify($title));
		$row["created_at"] = $now;
		$id = $db->insert(T_EDU_ITEMS, $row);
	}
	return array((int) $id);
}

function cl_edu_save_comic($in) {
	global $db;
	$cats = cl_edu_categories(); $sts = cl_edu_statuses();
	$id    = (int) (isset($in["id"]) ? $in["id"] : 0);
	$title = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) (isset($in["title"]) ? $in["title"] : "")))), 0, 190);
	$cat   = isset($in["category"]) ? $in["category"] : "";
	$sum   = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) (isset($in["summary"]) ? $in["summary"] : "")))), 0, 400);
	$cov_in = isset($in["cover"]) ? trim((string) $in["cover"]) : "";
	$cover = cl_edu_safe_image($cov_in);
	$st    = isset($in["status"]) ? $in["status"] : "draft";
	$pin   = !empty($in["pinned"]) && $in["pinned"] !== "0" && $in["pinned"] !== "false" ? 1 : 0;
	$keep = array();
	$raw = json_decode((string) (isset($in["panels"]) ? $in["panels"] : "[]"), true);
	if (!is_array($raw)) { $raw = array(); }
	$panels = cl_edu_panels_decode(json_encode($raw));
	foreach (array_values($raw) as $i => $rp) {
		if (!is_array($rp) || !isset($rp["bubbles"])) { continue; }
		$cb = cl_conv_clean_bubbles($rp["bubbles"], true);
		if ($cb[0] === null) { return array(null, "Case " . ($i + 1) . " : " . $cb[1], "panels"); }
	}
	foreach ($panels as $p) { if ($p["image"] !== "" || $p["caption"] !== "" || $p["dialogue"] !== "" || !empty($p["bubbles"])) { $keep[] = $p; } }

	if ($title === "") { return array(null, "Donnez un titre à la bande dessinée.", "title"); }
	if (!isset($cats[$cat])) { return array(null, "Choisissez une catégorie.", "category"); }
	if ($sum === "") { return array(null, "Le résumé est obligatoire.", "summary"); }
	if ($cov_in !== "" && $cover === "") { return array(null, "Adresse de couverture invalide.", "cover"); }
	if (!isset($sts[$st])) { $st = "draft"; }
	if (count($keep) < 1) { return array(null, "Ajoutez au moins une case.", "panels"); }
	if (count($keep) > 24) { return array(null, "24 cases au maximum.", "panels"); }
	if ($st == "published" && count($keep) < 2) { return array(null, "Une bande dessinée publiée compte au moins 2 cases.", "panels"); }

	$now = time();
	$row = array("title" => $title, "category" => $cat, "summary" => $sum, "cover" => $cover, "panels" => json_encode($keep, JSON_UNESCAPED_UNICODE), "status" => $st, "pinned" => $pin, "updated_at" => $now);
	if ($id) {
		if (!cl_edu_get_comic($id)) { return array(null, "Bande dessinée introuvable.", ""); }
		$db->where("id", $id)->update(T_EDU_COMICS, $row);
	} else {
		$row["slug"]       = cl_edu_unique_slug(T_EDU_COMICS, cl_edu_slugify($title));
		$row["created_at"] = $now;
		$id = $db->insert(T_EDU_COMICS, $row);
	}
	return array((int) $id);
}

function cl_edu_duplicate($table, $id) {
	global $db;
	$r = $table == T_EDU_COMICS ? cl_edu_get_comic($id) : cl_edu_get_item($id);
	if (!$r) { return 0; }
	unset($r["id"]);
	$r["title"]      = mb_substr("Copie de " . $r["title"], 0, 190);
	$r["slug"]       = cl_edu_unique_slug($table, cl_edu_slugify($r["title"]));
	$r["status"]     = "draft";
	$r["pinned"]     = 0;
	$r["created_at"] = $r["updated_at"] = time();
	return (int) $db->insert($table, $r);
}

/** Envoi d'image : JPG, PNG ou WebP, 3 Mo maximum, contrôlé par le contenu réel du fichier. */
function cl_edu_upload_image($file) {
	if (empty($file) || empty($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) { return array(null, "Aucun fichier reçu."); }
	if ($file["size"] > 3 * 1024 * 1024) { return array(null, "Image trop lourde (3 Mo maximum)."); }
	$fi   = new finfo(FILEINFO_MIME_TYPE);
	$mime = $fi->file($file["tmp_name"]);
	$ext  = array("image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp");
	if (!isset($ext[$mime])) { return array(null, "Format non accepté (JPG, PNG ou WebP)."); }
	if (!@getimagesize($file["tmp_name"])) { return array(null, "Fichier image illisible."); }
	$dir = cl_full_path("upload/edu");
	if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
	$name = "edu-" . date("Ymd") . "-" . substr(md5(uniqid("", true)), 0, 10) . "." . $ext[$mime];
	if (!@move_uploaded_file($file["tmp_name"], $dir . "/" . $name)) { return array(null, "Impossible d'enregistrer le fichier."); }
	return array("upload/edu/" . $name);
}
