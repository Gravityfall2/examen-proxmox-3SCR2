<?php
/*
 * examen-proxmox-3SCR2 — page de l'invité web du mini-SI.
 * Affiche l'identité du serveur (nom d'hôte, adresses) et l'état
 * de la connexion à l'invité BDD. Aucune dépendance externe.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
// Jamais de cache : chaque capture doit refléter un rendu réel.
header('Cache-Control: no-store, max-age=0');

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Paris');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Masque (255.255.255.0 ou ffff:ffff::) -> longueur de préfixe (24, 32…).
function prefixLength(string $mask): ?int
{
    $bin = $mask !== '' ? @inet_pton($mask) : false;
    if ($bin === false) {
        return null;
    }
    $bits = 0;
    foreach (str_split($bin) as $byte) {
        $bits += substr_count(decbin(ord($byte)), '1');
    }
    return $bits;
}

function short(string $text, int $max = 180): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    return strlen($text) > $max ? substr($text, 0, $max) . '…' : $text;
}

/* ---------- Identité du serveur ---------- */

$hostname   = gethostname() ?: 'inconnu';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? 'inconnue';
$serverPort = $_SERVER['SERVER_PORT'] ?? '';
$clientAddr = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
$software   = $_SERVER['SERVER_SOFTWARE'] ?? 'inconnu';
$renderedAt = new DateTimeImmutable('now');

// Toutes les adresses de l'invité (hors loopback et lien local IPv6).
$interfaces = [];
if (function_exists('net_get_interfaces')) {
    foreach (@net_get_interfaces() ?: [] as $name => $iface) {
        foreach ($iface['unicast'] ?? [] as $addr) {
            $ip = $addr['address'] ?? '';
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            if ($ip === '127.0.0.1' || $ip === '::1' || stripos($ip, 'fe80:') === 0) {
                continue;
            }
            $interfaces[] = [
                'name'    => (string) $name,
                'address' => $ip,
                'prefix'  => prefixLength((string) ($addr['netmask'] ?? '')),
                'family'  => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6',
            ];
        }
    }
}

// Certains serveurs (ex. php -S) ne renseignent pas SERVER_ADDR :
// on retombe alors sur la première IPv4 de l'invité.
if ($serverAddr === 'inconnue') {
    foreach ($interfaces as $if) {
        if ($if['family'] === 'IPv4') {
            $serverAddr = $if['address'];
            break;
        }
    }
}

/* ---------- Configuration BDD ---------- */

// Les messages n'affichent que le nom du fichier : jamais de chemin
// absolu, jamais le texte d'une erreur de syntaxe (il peut citer le mot de passe).
$configFile = null;
foreach (['config.php', 'config.example.php'] as $candidate) {
    if (is_file(__DIR__ . '/' . $candidate)) {
        $configFile = $candidate;
        break;
    }
}
$config = [];
$configError = null;
if ($configFile === null) {
    $configError = 'Aucun fichier de configuration (config.php ou config.example.php).';
} elseif (!is_readable(__DIR__ . '/' . $configFile)) {
    $configError = basename($configFile) . ' illisible (droits ?).';
} else {
    try {
        $loaded = require __DIR__ . '/' . $configFile;
        if (is_array($loaded)) {
            $config = $loaded;
        } else {
            $configError = basename($configFile) . ' ne retourne pas de tableau.';
        }
    } catch (Throwable $ex) {
        $configError = sprintf('%s illisible (%s, ligne %d).', basename($configFile), get_class($ex), $ex->getLine());
    }
}
$db = [
    'host'     => (string) ($config['host'] ?? '127.0.0.1'),
    'port'     => (int) ($config['port'] ?? 3306),
    'dbname'   => (string) ($config['dbname'] ?? ''),
    'user'     => (string) ($config['user'] ?? ''),
    'password' => (string) ($config['password'] ?? ''),
];

/* ---------- Test de la connexion BDD ---------- */

$dbResult = ['ok' => false, 'error' => null, 'version' => null, 'hostname' => null, 'now' => null, 'ms' => null];

if ($configError !== null) {
    $dbResult['error'] = $configError;
} elseif (!extension_loaded('pdo_mysql')) {
    $dbResult['error'] = "Extension PHP pdo_mysql absente (paquet Debian : php-mysql).";
} else {
    $start = hrtime(true);
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['dbname']),
            $db['user'],
            $db['password'],
            [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $row = $pdo->query('SELECT VERSION() AS version, NOW() AS now, @@hostname AS hostname')->fetch();
        $dbResult['ok']       = true;
        $dbResult['version']  = $row['version'] ?? '';
        $dbResult['hostname'] = $row['hostname'] ?? '';
        $dbResult['now']      = $row['now'] ?? '';
    } catch (Throwable $ex) {
        $dbResult['error'] = short($ex->getMessage());
    }
    $dbResult['ms'] = (int) round((hrtime(true) - $start) / 1e6);
    $pdo = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($hostname) ?> · examen-proxmox-3SCR2</title>
<style>
body { font-family: sans-serif; text-align: center; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
table { margin: 0 auto; }
dd { margin: 0 0 0.5rem; }
h2, dd, td { overflow-wrap: anywhere; }
</style>
</head>
<body>
<header>
    <h1>examen-proxmox-3SCR2</h1>
    <p>Mini-SI Proxmox · Équipe : Allen, Oumar, Vincent · Classe 3SCR2 (ESGI 3A)</p>
</header>

<main>
    <section>
        <h2>Serveur web : <?= e($hostname) ?></h2>
        <dl>
            <dt>Nom d’hôte</dt>
            <dd><?= e($hostname) ?></dd>
            <dt>IP du serveur</dt>
            <dd><?= e($serverAddr) ?></dd>
            <dt>IP du client</dt>
            <dd><?= e($clientAddr) ?></dd>
            <dt>Page générée le</dt>
            <dd><time datetime="<?= e($renderedAt->format(DATE_ATOM)) ?>"><?= e($renderedAt->format('d/m/Y H:i:s')) ?></time></dd>
            <dt>Logiciel serveur / PHP</dt>
            <dd><?= e($software) ?> / PHP <?= e(PHP_VERSION) ?></dd>
        </dl>

        <?php if ($interfaces): ?>
        <table>
            <caption>Adresses de l’invité</caption>
            <thead>
                <tr><th scope="col">Interface</th><th scope="col">Adresse / préfixe</th></tr>
            </thead>
            <tbody>
                <?php foreach ($interfaces as $if): ?>
                <tr>
                    <td><?= e($if['name']) ?></td>
                    <td><?= e($if['address'] . ($if['prefix'] !== null ? '/' . $if['prefix'] : '')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section>
        <h2>Base de données : <?= $dbResult['ok'] ? 'OK' : 'KO' ?></h2>
        <?php if ($dbResult['ok']): ?>
        <p>Connexion à la base établie.</p>
        <?php else: ?>
        <p>Connexion à la base impossible : <?= e($dbResult['error'] ?? 'erreur inconnue') ?></p>
        <?php endif; ?>
        <dl>
            <dt>Cible</dt>
            <dd><?= e($db['host'] . ':' . $db['port']) ?></dd>
            <dt>Base / utilisateur</dt>
            <dd><?= e($db['dbname']) ?> / <?= e($db['user']) ?></dd>
            <?php if ($dbResult['ok']): ?>
            <dt>Nom d’hôte BDD</dt>
            <dd><?= e($dbResult['hostname']) ?></dd>
            <dt>Moteur</dt>
            <dd><?= e($dbResult['version']) ?></dd>
            <dt>Heure de la BDD</dt>
            <dd><?= e($dbResult['now']) ?></dd>
            <?php endif; ?>
            <?php if ($dbResult['ms'] !== null): ?>
            <dt>Durée du test</dt>
            <dd><?= e($dbResult['ms']) ?> ms</dd>
            <?php endif; ?>
        </dl>
    </section>
</main>

<footer>
    <p>Configuration chargée : <?= e($configFile ?? 'aucune') ?></p>
</footer>
</body>
</html>
