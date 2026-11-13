<?php
# Ganho Forum : administration des contenus éducatifs
require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["edu_items"]      = cl_edu_get_items(false);
$cl["edu_categories"] = cl_edu_categories();
$cl["edu_audiences"]  = cl_edu_audiences();
$cl["http_res"]       = cl_template("cpanel/assets/edu_content/content");
