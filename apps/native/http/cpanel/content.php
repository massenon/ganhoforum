<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

if (empty($cl["is_logged"])) {
	cl_redirect("404");
}

else if(empty($cl['is_admin'])) {
	// Un·e modérateur·rice (rôle attribué dans Membres et rôles) n'accède qu'à la file des signalements.
	require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
	cl_adm_install();
	if (cl_adm_is_moderator() && fetch_or_get($_GET['section'], '') === 'moderation_queue') {
		$cl['is_moderator'] = true;
	}
	else {
		cl_redirect("404");
	}
}

$cl["page_title"] = cl_translate("Control panel");
$cl["page_desc"]  = $cl["config"]["description"];
$cl["page_kw"]    = $cl["config"]["keywords"];
$cl["pn"]         = "cpanel";
$cl["cp_section"] = fetch_or_get($_GET['section'], 'dashboard');
$section_handler  = cl_full_path(cl_strf('apps/native/http/cpanel/sections/%s.php', $cl["cp_section"]));

if (file_exists($section_handler)) {
	require_once($section_handler);

	echo cl_template("cpanel/content");
	exit();
}
else {
	cl_redirect("404");
}

