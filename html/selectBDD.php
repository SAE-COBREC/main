<?php

include("config1.php");

try {
    $pdo = new PDO("$driver:host=$serveur;dbname=$dbname", $user, $pass);
    
    foreach ($pdo->query(query: "SELECT * FROM cobrec1._compte", fetchMode: PDO::FETCH_ASSOC) as $row) {
        echo "<pre>";
        print_r($row);
        echo "<pre>";
    }

    $pdo = null;
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}

?>