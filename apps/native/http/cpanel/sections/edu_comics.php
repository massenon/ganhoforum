<?php
# Ganho Forum : administration des bandes dessinées
require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

$cl["app_statics"] = array("scripts" => array(cl_static_file_path("statics/ganho/js/gn-conv.js")), "styles" => array(cl_static_file_path("statics/ganho/css/gn-conv.css")));
$cl["edu_comics"]     = cl_edu_get_comics(false);
$cl["edu_categories"] = cl_edu_categories();
$cl["conv_colors"]    = cl_conv_colors();
$cl["conv_emotions"]  = cl_conv_emotions();
$cl["conv_tails"]     = cl_conv_tails();
$cl["http_res"]       = cl_template("cpanel/assets/edu_comics/content");
