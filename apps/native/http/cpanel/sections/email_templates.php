<?php 
# @*************************************************************************@
# @ Ganho Forum : modèles d'e-mail
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
cl_cmp_install();

$cl["app_statics"] = array("scripts" => array(), "styles" => array());

$cl["cmp_templates"] = cl_cmp_templates();
$cl["cmp_variables"] = cl_cmp_variables();
$cl["http_res"]      = cl_template("cpanel/assets/email_templates/content");
