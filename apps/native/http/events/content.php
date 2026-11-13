<?php
# Événements et groupes de parole (calendrier public, géré dans l'administration > Engagement).
if (empty($cl["is_logged"])) {
	cl_redirect("guest");
}
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["page_title"] = "Événements";
$cl["page_desc"]  = "Groupes de parole en ligne, ateliers et rencontres de la communauté GANXÔ.";
$cl["page_kw"]    = $cl["config"]["keywords"];
$cl["pn"]         = "events";
$cl["sbr"]        = false;
$cl["sbl"]        = true;
$cl["gn_events"]  = $db->rawQuery("SELECT id, title, description, kind, starts_at, location, status FROM `" . T_ADM_EVENTS . "` WHERE status IN ('planifie','en_cours') AND (starts_at IS NULL OR starts_at >= ?) ORDER BY (starts_at IS NULL), starts_at ASC LIMIT 60", array(time() - 6 * 3600));
$cl["http_res"]   = cl_template("events/content");
