<?php
/*
 * Exemple de configuration de la connexion à la base (invité BDD).
 * Copier ce fichier en config.php puis adapter les valeurs :
 *   cp config.example.php config.php
 * config.php est ignoré par git (il contient le vrai mot de passe).
 */
return [
    'host'     => '10.10.10.20',   // IP de l'invité BDD sur le bridge interne
    'port'     => 3306,
    'dbname'   => 'minisi',
    'user'     => 'minisi_app',
    'password' => 'changez-moi',
];
