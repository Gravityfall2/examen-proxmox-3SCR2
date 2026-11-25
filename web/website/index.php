<?php
date_default_timezone_set('Europe/Paris');

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$dbHost = null;
try {
    $file = file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php';
    $name = basename($file);
    $cfg = null;
    if (!file_exists($file)) {
        error_log('minisi: aucun fichier de config');
    } elseif (!is_readable($file)) {
        error_log('minisi: ' . $name . ' illisible (droits ?)');
    } else {
        try {
            $cfg = require $file;
        } catch (Throwable $e) {
            // pas de getMessage() : une ParseError peut contenir un bout du mot de passe
            error_log('minisi: ' . $name . ' ' . get_class($e) . ' ligne ' . $e->getLine());
            $cfg = false;
        }
    }
    if ($cfg === false || $cfg === null) {
        // erreur déjà journalisée
    } elseif (!is_array($cfg) || array_diff(array('host', 'port', 'dbname', 'user', 'password'), array_keys($cfg))) {
        error_log('minisi: ' . $name . ' invalide');
    } elseif (!extension_loaded('pdo_mysql')) {
        error_log('minisi: extension pdo_mysql absente (apt install php-mysql)');
    } else {
        try {
            $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], array(
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ));
            $dbHost = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
        } catch (PDOException $e) {
            error_log('minisi: PDO (' . $name . ') ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('minisi: ' . get_class($e) . ' ligne ' . $e->getLine());
}

$host = gethostname();
$ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '?';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($host) ?> - examen-proxmox-3SCR2</title>
<style>body { font-family: sans-serif; text-align: center; margin: 2rem auto; max-width: 40rem; padding: 0 1rem; }</style>
</head>
<body>
<h1>examen-proxmox-3SCR2</h1>
<p>Allen, Oumar, Vincent — 3SCR2</p>
<p>Nom d'hôte : <?= h($host) ?></p>
<p>IP du serveur : <?= h($ip) ?></p>
<p>Date : <?= h(date('d/m/Y H:i:s')) ?></p>
<p>Base de données : <?= $dbHost !== null ? 'OK — ' . h($dbHost) : 'KO' ?></p>
</body>
</html>
