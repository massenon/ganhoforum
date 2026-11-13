<?php
# Ganho Forum : annuaire des membres, rôles et badges
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["adm_roles"] = cl_adm_roles_labels();
$cl["adm_badges"] = cl_adm_badge_labels();
$cl["http_res"] = cl_template("cpanel/assets/members_roles/content");
