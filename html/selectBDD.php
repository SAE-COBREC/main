<?php

include("/config1.php");

try {
    $dsn = "$driver:host=$serveur;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Définir le schéma par défaut si disponible (cobrec1)
    try { $pdo->exec("SET search_path TO cobrec1"); } catch (Throwable $t) { /* ignore */ }
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}