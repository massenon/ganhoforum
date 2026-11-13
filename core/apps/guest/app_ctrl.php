<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

function cl_get_total_users() {
	global $db, $cl;

	$db  = $db->where('active', array('1', '2'), 'IN');
	$qr  = $db->getValue(T_USERS, 'COUNT(*)');
	$num = 0;

	if (is_posnum($qr)) {
		$num = $qr;
	}

	return $num;
}