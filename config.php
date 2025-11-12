<?php
// Configuration de la connexion PostgreSQL
$host = '10.253.5.101';  // Adresse du serveur sae-01 visible dans l'image
$port = '5432';           // Port PostgreSQL depuis serveurs.sh
$dbname = 'saedb';        // Nom de la base de données
$user = 'sae';            // Utilisateur depuis serveurs.sh
$password = 'kira13';     // Mot de passe depuis serveurs.sh
$schema = 'cobrec1';      // Schéma visible dans pgAdmin

try {
    // Connexion PDO PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Définir le schéma par défaut
    $pdo->exec("SET search_path TO $schema");
    
    echo "✅ Connexion réussie à la base $dbname, schéma $schema !";
    
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}
?>
