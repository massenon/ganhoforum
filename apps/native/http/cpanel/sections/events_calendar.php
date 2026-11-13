<?php
# Ganho Forum : page CRUD générique (suite admin) — entité « events »
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["adm_entity"] = "events";
$ents = cl_adm_entities();
$cl["adm_def"]    = $ents["events"];
$cl["adm_rows"]   = cl_adm_list("events");
$cl["http_res"]   = cl_template("cpanel/assets/adm_crud/content");
