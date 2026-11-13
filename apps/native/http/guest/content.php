<?php 
# @*************************************************************************@
# @ Software author: Dr MASSENON Rhodes                                     @
# @ Author_url : www.massenonrhodes.com                                     @
# @ Author E-mail: rarhodes06@gmail.com                                     @
# @*************************************************************************@
# @ Ganho Forum                                                             @
# @*************************************************************************@

if (not_empty($cl["is_logged"])) {
	cl_redirect("home");
}

require_once(cl_full_path("core/apps/guest/app_ctrl.php"));

$cl["invite_code"] = fetch_or_get($_GET["invite_code"], false);
$cl["auth_type"]   = fetch_or_get($_GET["auth"], "login");
$cl["auth_type"]   = (in_array($cl["auth_type"], array("login", "signup", "forgot_pass", "reset_pass"))) ? $cl["auth_type"] : "login";
$cl["page_titles"] = array(
	"login"       => "Connexion",
	"signup"      => "Créer un compte",
	"forgot_pass" => "Mot de passe oublié",
	"reset_pass"  => "Nouveau mot de passe"
);
$cl["page_title"]  = $cl["page_titles"][$cl["auth_type"]] . " | Forum Ganho";
$cl["page_desc"]   = "Connectez-vous ou créez votre compte Forum Ganho : un espace bienveillant pour la communauté LGBTQ pour discuter et partager en confiance.";
$cl["page_kw"]     = $cl["config"]["keywords"];
$cl["pn"]          = "guest";
$cl['em_code']     = ((not_empty($_GET['em_code']) && cl_verify_reset_token($_GET['em_code'])) ? cl_text_secure($_GET['em_code']) : null);
$cl["sbr"]         = false;
$cl["sbl"]         = false;
$cl["invite_code_status"] = (not_empty($cl["invite_code"])) ? cl_verify_invite_code($cl["invite_code"]) : false;
$cl["http_res"] = cl_template("guest/content");
