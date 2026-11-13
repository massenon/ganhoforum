<?php
# Copiez ce fichier en core/settings.php et renseignez vos valeurs.
# core/settings.php est ignoré par git : ne le publiez jamais.

# Hôte MySQL
$sql_db_host = "localhost";

# Utilisateur MySQL
$sql_db_user = "utilisateur_mysql";

# Mot de passe MySQL
$sql_db_pass = "mot_de_passe_mysql";

# Nom de la base
$sql_db_name = "nom_de_la_base";

# URL du forum (sans slash final)
$site_url    = "https://forum.ganho.app";

# API GANXÔ Alerte VBG (alerte en chat)
# Le secret doit être identique à FORUM_ALERT_SECRET dans le .env de ganhovbg.
# Générer : php -r "echo bin2hex(random_bytes(32));"
$ganho_api_url    = "https://ganho.app/api";
$ganho_api_secret = "remplacer_par_64_caracteres_hexadecimaux";
?>
