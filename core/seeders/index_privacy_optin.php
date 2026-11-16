<?php
# Indexation des membres : passage à l'opt-in (désactivée tant que la personne n'a pas choisi « Oui »).
#
# Contexte : « visibilité dans la recherche » (cl_users.index_privacy) valait « Y » par défaut pour tous les comptes,
# sans choix explicite. Les nouveaux comptes sont maintenant créés avec « N » (code d'inscription). Ce script :
#   1. change la valeur par défaut de la colonne en « N » (sécurité si une inscription oublie le champ) ;
#   2. SEULEMENT si RESET_EXISTING=1 : remet à « N » les comptes existants, car « Y » n'a jamais été un choix.
#      Les membres peuvent ensuite l'activer dans Paramètres > Confidentialité.
#
# Lancement (sauvegarder la base avant) :
#   GANHO_ALLOW_SEED=1 php core/seeders/index_privacy_optin.php                       (étape 1 seulement)
#   GANHO_ALLOW_SEED=1 RESET_EXISTING=1 php core/seeders/index_privacy_optin.php      (étapes 1 et 2)
# Idempotent : relançable sans effet de bord.

if (getenv("GANHO_ALLOW_SEED") !== "1") {
	fwrite(STDERR, "Refusé : lancez avec GANHO_ALLOW_SEED=1 (sauvegardez d'abord la base).\n");
	exit(1);
}

chdir(dirname(__DIR__, 2));
require_once("core/settings.php");

mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name);
if ($m->connect_errno) {
	fwrite(STDERR, "Connexion impossible : " . $m->connect_error . "\n");
	exit(1);
}
$m->set_charset("utf8mb4");

$m->query("ALTER TABLE `cl_users` MODIFY `index_privacy` ENUM('Y','N') NOT NULL DEFAULT 'N'");
echo "Valeur par défaut de index_privacy : N\n";

$before = $m->query("SELECT SUM(index_privacy='Y') y, SUM(index_privacy='N') n FROM cl_users")->fetch_assoc();
echo "Avant : Y=" . (int) $before["y"] . "  N=" . (int) $before["n"] . "\n";

if (getenv("RESET_EXISTING") === "1") {
	$m->query("UPDATE `cl_users` SET index_privacy = 'N' WHERE index_privacy <> 'N'");
	echo "Comptes remis à N : " . $m->affected_rows . "\n";
	$after = $m->query("SELECT SUM(index_privacy='Y') y, SUM(index_privacy='N') n FROM cl_users")->fetch_assoc();
	echo "Après : Y=" . (int) $after["y"] . "  N=" . (int) $after["n"] . "\n";
} else {
	echo "Comptes existants inchangés (relancer avec RESET_EXISTING=1 pour les remettre à N).\n";
}
$m->close();
