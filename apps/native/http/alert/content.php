<?php
# Page « Créer une alerte VBG » : conversation intégrée, accessible avec ou sans compte.
$cl["page_title"] = "Créer une alerte VBG";
$cl["page_desc"]  = "Parlez à l’assistant GANXÔ : votre alerte est transmise de façon sécurisée à l’équipe GANXÔ Alerte VBG.";
$cl["page_kw"]    = $cl["config"]["keywords"];
$cl["pn"]         = "alert";
$cl["sbr"]        = false;
$cl["sbl"]        = true;
$cl["http_res"]   = cl_template("alert/content");
