<?php 
# @*************************************************************************@
# @ Ganho Forum : campagnes e-mail (diffusion en masse)
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
cl_cmp_install();

$cl["app_statics"] = array("scripts" => array(), "styles" => array());

$cl["cmp_campaigns"] = cl_cmp_campaigns();
$cl["cmp_templates"] = cl_cmp_templates();
$cl["cmp_kpis"]      = cl_cmp_kpis();
$cl["cmp_audiences"] = cl_cmp_audiences();
$cl["http_res"]      = cl_template("cpanel/assets/campaigns/content");
