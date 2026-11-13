<?php
# Ganho Forum : file des signalements unifiée
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["http_res"] = cl_template("cpanel/assets/moderation_queue/content");
