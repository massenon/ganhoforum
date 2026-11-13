<?php
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/post_reports/app_ctrl.php"));

$cl['total_reports'] = cl_admin_get_total_post_reports();
$cl['post_reports']  = cl_admin_get_post_reports();
$cl['http_res']      = cl_template("cpanel/assets/post_reports/content");