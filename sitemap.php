<?php
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

// sitemap.xml dynamique : pages publiques, discussions et profils dont l'auteur a accepté l'indexation
// (réglage « visibilité dans la recherche » = index_privacy Y). Les pages de connexion (noindex) en sont exclues.
require_once(__DIR__ . "/core/settings.php");

$base = rtrim((isset($site_url) ? $site_url : ""), "/");
$urls = array();

$static = array(
	"about_us"       => "themes/default/apps/about_us/content.phtml",
	"faqs"           => "themes/default/apps/faqs/content.phtml",
	"terms_of_use"   => "themes/default/apps/terms/content.phtml",
	"privacy_policy" => "themes/default/apps/privacy_policy/content.phtml",
	"cookies_policy" => "themes/default/apps/cookies_policy/content.phtml"
);

foreach ($static as $path => $tpl) {
	$file  = __DIR__ . "/" . $tpl;
	$mtime = (file_exists($file) ? filemtime($file) : time());
	$urls[] = array($base . "/" . $path, gmdate("Y-m-d", $mtime), "monthly", "0.5");
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);

if (!$mysqli->connect_errno) {
	$mysqli->set_charset("utf8mb4");

	$qr = $mysqli->query("SELECT p.id, p.time FROM cl_publications p JOIN cl_users u ON u.id = p.user_id WHERE p.target = 'publication' AND p.status = 'active' AND p.priv_wcs = 'everyone' AND u.index_privacy = 'Y' AND u.profile_privacy = 'everyone' AND u.active = '1' ORDER BY p.id DESC LIMIT 40000");

	if ($qr) {
		while ($row = $qr->fetch_assoc()) {
			$ts = (is_numeric($row['time']) && $row['time'] > 0) ? (int) $row['time'] : time();
			$urls[] = array($base . "/thread/" . (int) $row['id'], gmdate("Y-m-d", $ts), "weekly", "0.6");
		}
	}

	$qr = $mysqli->query("SELECT username, last_active FROM cl_users WHERE index_privacy = 'Y' AND profile_privacy = 'everyone' AND active = '1' ORDER BY followers DESC LIMIT 5000");

	if ($qr) {
		while ($row = $qr->fetch_assoc()) {
			$ts = (is_numeric($row['last_active']) && $row['last_active'] > 0) ? (int) $row['last_active'] : time();
			$urls[] = array($base . "/" . rawurlencode($row['username']), gmdate("Y-m-d", $ts), "weekly", "0.4");
		}
	}

	# Contenus éducatifs et bandes dessinées publiés (tables créées à la demande : requêtes ignorées si absentes)
	$edu_last = 0;
	$edu_rows = array();
	$qr = $mysqli->query("SELECT slug, updated_at FROM cl_edu_items WHERE status = 'published' AND audience = 'all' ORDER BY updated_at DESC");
	if ($qr) {
		while ($row = $qr->fetch_assoc()) {
			$edu_rows[] = array($base . "/contenus-educatifs/" . rawurlencode($row['slug']), (int) $row['updated_at']);
			$edu_last = max($edu_last, (int) $row['updated_at']);
		}
	}
	$bd_last = 0;
	$bd_rows = array();
	$qr = $mysqli->query("SELECT slug, updated_at FROM cl_edu_comics WHERE status = 'published' ORDER BY updated_at DESC");
	if ($qr) {
		while ($row = $qr->fetch_assoc()) {
			$bd_rows[] = array($base . "/bandes-dessinees/" . rawurlencode($row['slug']), (int) $row['updated_at']);
			$bd_last = max($bd_last, (int) $row['updated_at']);
		}
	}
	if ($edu_rows) {
		$urls[] = array($base . "/contenus-educatifs", gmdate("Y-m-d", $edu_last), "weekly", "0.8");
		foreach ($edu_rows as $r) { $urls[] = array($r[0], gmdate("Y-m-d", $r[1]), "monthly", "0.7"); }
	}
	if ($bd_rows) {
		$urls[] = array($base . "/bandes-dessinees", gmdate("Y-m-d", $bd_last), "weekly", "0.8");
		foreach ($bd_rows as $r) { $urls[] = array($r[0], gmdate("Y-m-d", $r[1]), "monthly", "0.7"); }
	}

	$mysqli->close();
}

header("Content-Type: application/xml; charset=utf-8");
header("Cache-Control: public, max-age=3600");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
	echo "\t<url><loc>" . htmlspecialchars($u[0], ENT_XML1 | ENT_QUOTES, "UTF-8") . "</loc><lastmod>" . $u[1] . "</lastmod><changefreq>" . $u[2] . "</changefreq><priority>" . $u[3] . "</priority></url>\n";
}

echo "</urlset>\n";
