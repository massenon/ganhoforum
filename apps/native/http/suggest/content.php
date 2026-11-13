<?php
# Boîte à suggestions : formulaire minimal pour les membres connectés.
$cl["page_title"] = "Boîte à suggestions";
$cl["page_desc"]  = "Proposez une idée ou un retour pour améliorer le forum GANXÔ.";
$cl["page_kw"]    = $cl["config"]["keywords"];
$cl["pn"]         = "suggest";
$cl["sbr"]        = false;
$cl["sbl"]        = true;
$cl["http_res"]   = cl_template("suggest/content");
