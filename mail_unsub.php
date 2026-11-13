<?php
# Désinscription des informations envoyées par les campagnes e-mail (lien personnel : jeton du journal d'envoi).
# GET : page de confirmation ; POST : enregistre le refus. Aucune donnée personnelle affichée.
require_once("core/web_req_init.php");
require_once(cl_full_path("core/apps/cpanel/campaigns/app_ctrl.php"));
cl_cmp_install();
$t   = substr(preg_replace('/[^a-f0-9]/', '', (string) (isset($_REQUEST["t"]) ? $_REQUEST["t"] : "")), 0, 32);
$row = $t !== "" ? $db->rawQueryOne("SELECT user_id FROM `" . T_MAIL_LOG . "` WHERE token = ?", array($t)) : null;
$done = false;
if ($row && $_SERVER["REQUEST_METHOD"] === "POST") {
	$db->rawQuery("REPLACE INTO `" . T_MAIL_OPTOUT . "` (user_id, t) VALUES (?, ?)", array((int) $row["user_id"], time()));
	$done = true;
}
header("Content-Type: text/html; charset=UTF-8");
header("X-Robots-Tag: noindex");
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="theme-color" content="#0B0B14"><title>Désinscription | Forum Ganho</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#F8F1FF;color:#17132B;font-family:Arial,"Liberation Sans",Roboto,sans-serif;line-height:1.55}main{max-width:30rem;background:#fff;border:1px solid #E6DFF0;border-radius:24px;padding:32px;box-shadow:0 12px 32px rgba(23,19,43,.12);position:relative;overflow:hidden}main::before{content:"";position:absolute;left:0;right:0;top:0;height:6px;background:linear-gradient(90deg,#5B2BB8,#2B33C9,#0A66E8,#0F8B6D,#3F9A1A,#FFBE05,#F66100,#E8381A)}h1{margin:8px 0 12px;font:800 1.4rem/1.25 Montserrat,system-ui,sans-serif}p{margin:0 0 20px;color:#55506B}button,a.b{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 28px;border:0;border-radius:999px;background:#2B33C9;color:#fff;font:700 1rem Montserrat,system-ui,sans-serif;cursor:pointer;text-decoration:none}button:focus-visible,a.b:focus-visible{outline:3px solid #2B33C9;outline-offset:3px}</style></head>
<body><main>
<?php if ($done): ?>
<h1>C'est noté</h1><p>Vous ne recevrez plus les informations envoyées par l'équipe du forum Ganho. Vous pouvez continuer à utiliser le forum normalement, et les e-mails liés à votre compte (réinitialisation du mot de passe…) continuent de vous parvenir.</p>
<a class="b" href="<?php echo cl_cmp_h(cl_link("/")); ?>">Retour au forum</a>
<?php elseif ($row): ?>
<h1>Ne plus recevoir ces informations ?</h1><p>Confirmez pour arrêter de recevoir les informations envoyées par l'équipe du forum Ganho. Cela ne supprime pas votre compte.</p>
<form method="post" action=""><input type="hidden" name="t" value="<?php echo cl_cmp_h($t); ?>"><button type="submit">Confirmer la désinscription</button></form>
<?php else: ?>
<h1>Lien invalide</h1><p>Ce lien de désinscription n'est plus valable. Si vous recevez encore des messages que vous ne souhaitez pas, écrivez-nous depuis la boîte à suggestions du forum.</p>
<a class="b" href="<?php echo cl_cmp_h(cl_link("/")); ?>">Retour au forum</a>
<?php endif; ?>
</main></body></html>
