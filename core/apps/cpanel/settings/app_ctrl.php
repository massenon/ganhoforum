<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

function cl_admin_save_config($key = "none", $val = "none") {
	global $db, $cl;

    if (in_array($key, array_keys($cl['config']))) {
        $db = $db->where('name', $key);
        $qr = $db->update(T_CONFIGS, array(
        	'value' => $val
        ));
    }
    else{
        return false;
    }
}