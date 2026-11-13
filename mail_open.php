<?php
# Pixel d'ouverture des campagnes e-mail : enregistre la première ouverture puis renvoie un GIF 1x1.
require_once("core/web_req_init.php");
require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
if (!empty($_GET["t"])) {
	cl_cmp_install();
	cl_cmp_open($_GET["t"]);
}
header("Content-Type: image/gif");
header("Cache-Control: no-store, no-cache, must-revalidate");
echo base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
exit;
