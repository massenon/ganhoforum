<?php
# Contenus éducatifs : liste filtrable par catégorie et page de détail d'une fiche.
require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

$cl["pn"]  = "educational";
$cl["sbr"] = false;
$cl["sbl"] = true;
$cl["page_kw"] = $cl["config"]["keywords"];
$edu_slug = isset($_GET["slug"]) ? (string) $_GET["slug"] : "";

if ($edu_slug !== "") {
	$item = cl_edu_get_item_by_slug($edu_slug);
	if (!$item) { cl_redirect("404"); }
	$cats = cl_edu_categories(); $aud = cl_edu_audiences();
	$item["category_label"] = isset($cats[$item["category"]]) ? $cats[$item["category"]] : $item["category"];
	$item["audience_label"] = isset($aud[$item["audience"]]) ? $aud[$item["audience"]] : "";
	$item["image_url"]      = cl_edu_image_url($item["image"]);
	$item["minutes"]        = cl_edu_reading_minutes($item["body"]);
	$item["date_fr"]        = date("d/m/Y", (int) $item["updated_at"]);
	$item["html"]           = cl_edu_render_body($item["body"], $toc);
	$cl["edu_item"] = $item;
	$cl["edu_toc"]  = $toc;
	$related = array();
	foreach (cl_edu_get_items(true, $item["category"]) as $r) { if ($r["id"] != $item["id"] && count($related) < 3) { $related[] = $r; } }
	$cl["edu_related"] = $related;
	$cl["page_title"]  = $item["title"];
	$cl["page_desc"]   = $item["summary"];
	$cl["http_res"]    = cl_template("educational/detail");
} else {
	$cat = isset($_GET["cat"]) ? (string) $_GET["cat"] : "";
	$cl["edu_cat"]        = isset(cl_edu_categories()[$cat]) ? $cat : "";
	$cl["edu_categories"] = cl_edu_categories();
	$cl["edu_all"]        = cl_edu_get_items(true);
	$cl["edu_items"]      = cl_edu_get_items(true, $cl["edu_cat"]);
	$cl["page_title"]     = "Contenus éducatifs";
	$cl["page_desc"]      = "Fiches pratiques pour comprendre et agir face aux violences numériques et au harcèlement en ligne.";
	$cl["http_res"]       = cl_template("educational/content");
}
