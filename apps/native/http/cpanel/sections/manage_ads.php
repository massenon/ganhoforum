<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

require_once(cl_full_path("core/apps/cpanel/ads/app_ctrl.php"));

$cl['user_ads'] = cl_admin_get_user_ads(array('limit' => 10));
$cl['http_res'] = cl_template("cpanel/assets/manage_ads/content");