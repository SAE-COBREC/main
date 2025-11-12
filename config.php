<?php
$host = '127.0.0.1';
$port = '5432';
$dbname = 'sae_db';
$user = 'saeuser';
$password = 'SaE_pass_123';

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
