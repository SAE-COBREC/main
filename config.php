<?php
$host = '127.0.0.1';
$port = '5432';
$dbname = 'base_sae';
$user = 'nom_utilisateur';
$password = 'motdepasse';

if (!class_exists('PDO')) {
    die("❌ Erreur de connexion : l'extension PDO n'est pas installée/activée dans PHP.\n" .
        "Sur Debian/Ubuntu : sudo apt install php-pdo (ou php-<version>-pdo).\n" .
        "Sur Windows : activez l'extension php_pdo dans php.ini.");
}

$available = PDO::getAvailableDrivers();
if (!in_array('pgsql', $available, true)) {
    // Guide rapide pour corriger : linux / wsl / windows
    die("❌ Erreur de connexion : pilote PDO pgsql introuvable.\n" .
        "Sur WSL/Debian/Ubuntu installez : sudo apt update && sudo apt install php-pgsql\n" .
        "ou sudo apt install php8.3-pgsql (adapter la version PHP).\n" .
        "Sur Windows, activez extension=php_pdo_pgsql.dll dans php.ini ou installez la version de PHP avec pdo_pgsql.\n" .
        "Après installation, redémarrez le serveur PHP/Apache/FPM et réessayez.");
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}
?>