<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

$page = fetch_or_get($_GET['page'], 'terms');

if (in_array($page, array('terms','privacy_policy','cookies_policy','about_us','faqs','delete_account')) != true) {
	cl_not_found();
	return;
}

$page_titles         = array(
	'terms'          => cl_translate('Terms of Use'), 
	'privacy_policy' => cl_translate('Privacy policy'), 
	'cookies_policy' => cl_translate('Cookies policy'), 
	'about_us'       => cl_translate('About us'), 
	'faqs'           => "Foire aux questions",
	'delete_account' => "Supprimer mon compte et mes données"
);

$cl["page_title"] = $page_titles[$page];
$cl["page_desc"]  = $cl["config"]["description"];
$cl["page_kw"]    = $cl["config"]["keywords"];

if ($page == "delete_account") {
	$cl["page_desc"] = "Supprimer votre compte GANHO Forum et vos données : depuis l'application, le site ou par e-mail, et ce qui est conservé.";
}
$cl["pn"]         = "stat_pages";
$cl["sbr"]        = true;
$cl["sbl"]        = true;
$cl["http_res"]   = cl_template(cl_strf("%s/content",$page));


