<?php
# @*************************************************************************@
# @ Ganho Forum : actions AJAX des contenus éducatifs et bandes dessinées.   @
# @ Admin uniquement ; appelé pour les actions edu_* (apps/native/ajax/cpanel)@
# @*************************************************************************@

require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

$data["status"] = 400;

if ($action == "edu_list") {
	$data["status"] = 200;
	$data["items"]  = cl_edu_get_items(false);
	$data["comics"] = cl_edu_get_comics(false);
}

else if ($action == "edu_item_save") {
	$r = cl_edu_save_item($_POST);
	if ($r[0]) { $data["status"] = 200; $data["id"] = $r[0]; $data["items"] = cl_edu_get_items(false); }
	else { $data["message"] = $r[1]; $data["field"] = $r[2]; }
}

else if ($action == "edu_comic_save") {
	$r = cl_edu_save_comic($_POST);
	if ($r[0]) { $data["status"] = 200; $data["id"] = $r[0]; $data["comics"] = cl_edu_get_comics(false); }
	else { $data["message"] = $r[1]; $data["field"] = $r[2]; }
}

else if ($action == "edu_item_delete" || $action == "edu_comic_delete") {
	$id = (int) fetch_or_get($_POST["id"], 0);
	if ($id) {
		$db->where("id", $id)->delete($action == "edu_item_delete" ? T_EDU_ITEMS : T_EDU_COMICS);
		$data["status"] = 200;
		$data["items"]  = cl_edu_get_items(false);
		$data["comics"] = cl_edu_get_comics(false);
	}
}

else if ($action == "edu_item_duplicate" || $action == "edu_comic_duplicate") {
	$new = cl_edu_duplicate($action == "edu_item_duplicate" ? T_EDU_ITEMS : T_EDU_COMICS, (int) fetch_or_get($_POST["id"], 0));
	if ($new) {
		$data["status"] = 200; $data["id"] = $new;
		$data["items"]  = cl_edu_get_items(false);
		$data["comics"] = cl_edu_get_comics(false);
	}
}

else if ($action == "edu_item_toggle" || $action == "edu_comic_toggle") {
	$tbl = $action == "edu_item_toggle" ? T_EDU_ITEMS : T_EDU_COMICS;
	$id  = (int) fetch_or_get($_POST["id"], 0);
	$row = $tbl == T_EDU_ITEMS ? cl_edu_get_item($id) : cl_edu_get_comic($id);
	if ($row) {
		$to = $row["status"] == "published" ? "draft" : "published";
		if ($to == "published" && $tbl == T_EDU_COMICS && count(cl_edu_panels_decode($row["panels"])) < 2) {
			$data["message"] = "Une bande dessinée publiée compte au moins 2 cases.";
		} else {
			$db->where("id", $id)->update($tbl, array("status" => $to, "updated_at" => time()));
			$data["status"] = 200;
			$data["items"]  = cl_edu_get_items(false);
			$data["comics"] = cl_edu_get_comics(false);
		}
	}
}

else if ($action == "edu_upload") {
	$r = cl_edu_upload_image(isset($_FILES["file"]) ? $_FILES["file"] : array());
	if ($r[0]) { $data["status"] = 200; $data["path"] = $r[0]; $data["url"] = cl_link($r[0]); }
	else { $data["message"] = $r[1]; }
}

else {
	$data["message"] = "Action inconnue.";
}
