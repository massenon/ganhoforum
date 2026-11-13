<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

$cl["app_statics"] = array(
	"scripts" => array(
		cl_static_file_path("apps/cpanel/statics/js/libs/bootstrap-select-v1.13.9.min.js"),
		cl_static_file_path("statics/js/libs/jquery-plugins/jquery.form-v4.2.2.min.js")
	),
	"styles" => array(
		cl_static_file_path("apps/cpanel/statics/css/libs/bootstrap-select-v1.13.9.min.css")
	)
);

$cl['http_res'] = cl_template("cpanel/assets/payment_gateways/content");