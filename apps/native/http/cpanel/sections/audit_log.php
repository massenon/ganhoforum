<?php
# Ganho Forum : page CRUD générique (suite admin) — entité « audit »
require_once(cl_full_path("core/apps/cpanel/admin_suite/app_ctrl.php"));
cl_adm_install();
$cl["app_statics"] = array("scripts" => array(), "styles" => array());
$cl["adm_entity"] = "audit";
$ents = cl_adm_entities();
$cl["adm_def"]    = $ents["audit"];
$cl["adm_rows"]   = cl_adm_list("audit");
$cl["http_res"]   = cl_template("cpanel/assets/adm_crud/content");
