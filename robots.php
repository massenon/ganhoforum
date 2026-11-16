<?php
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

// robots.txt dynamique : le Sitemap est une URL absolue dérivée de la configuration ($site_url dans core/settings.php).
require_once(__DIR__ . "/core/settings.php");

$base = rtrim((isset($site_url) ? $site_url : ""), "/");

header("Content-Type: text/plain; charset=utf-8");
header("Cache-Control: public, max-age=3600");

echo "User-agent: *\n";
echo "Disallow: /apps\n";
echo "Disallow: /core\n";
echo "Disallow: /install\n";
echo "Disallow: /site_backups\n";
echo "Disallow: /themes/default/apps\n";
echo "Disallow: /admin_panel\n";
echo "Disallow: /native_api/\n";
echo "Disallow: /mobile_api/\n";
echo "Disallow: /api.php\n";
echo "Disallow: /api/mobile/\n";
echo "Disallow: /mobile_v1.php\n";
echo "Disallow: /oauth\n";
echo "Disallow: /settings\n";
echo "Disallow: /logout\n";
echo "Disallow: /*?spa_load=\n";
echo "Allow: /upload/\n";
echo "\n";

if ($base !== "") {
	echo "Sitemap: " . $base . "/sitemap.xml\n";
}
