<?php
# Ganho Forum : page CRUD générique (suite admin) — entité « announcements »
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["adm_entity"] = "announcements";
$ents = cl_adm_entities();
$cl["adm_def"]    = $ents["announcements"];
$cl["adm_rows"]   = cl_adm_list("announcements");
$cl["http_res"]   = cl_template("cpanel/assets/adm_crud/content");
