<?php
# Bandes dessinées : bibliothèque et lecteur (case par case ou défilement).
require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

$cl["pn"]  = "comics";
$cl["sbr"] = false;
$cl["sbl"] = true;
$cl["page_kw"] = $cl["config"]["keywords"];
$bd_slug = isset($_GET["slug"]) ? (string) $_GET["slug"] : "";

if ($bd_slug !== "") {
	$row = cl_edu_get_comic_by_slug($bd_slug);
	if (!$row) { cl_redirect("404"); }
	$cats = cl_edu_categories();
	$row["category_label"] = isset($cats[$row["category"]]) ? $cats[$row["category"]] : $row["category"];
	$row["panels_list"]    = cl_edu_panels_decode($row["panels"]);
	foreach ($row["panels_list"] as $i => $p) { if (isset($p["image"])) { $row["panels_list"][$i]["image_url"] = cl_edu_image_url($p["image"]); } }
	$cl["bd_item"]    = $row;
	$cl["bd_others"]  = array_slice(array_values(array_filter(cl_edu_get_comics(true), function ($c) use ($row) { return $c["id"] != (int) $row["id"]; })), 0, 3);
	$cl["page_title"] = $row["title"];
	$cl["page_desc"]  = $row["summary"];
	$cl["http_res"]   = cl_template("comics/detail");
} else {
	$cl["bd_items"]   = cl_edu_get_comics(true);
	$cl["page_title"] = "Bandes dessinées";
	$cl["page_desc"]  = "De courtes bandes dessinées pour comprendre les violences numériques et savoir réagir.";
	$cl["http_res"]   = cl_template("comics/content");
}
