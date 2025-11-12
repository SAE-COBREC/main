<?php

include("config.php");

try {
    $dbh = new PDO("$driver:host=$serveur;dbname=$dbname", $user, $pass);
    
    foreach ($dbh->query(query: "SELECT * FROM sae34.cobrec1._compte", fetchMode: PDO::FETCH_ASSOC) as $row) {
        echo "<pre>";
        print_r($row);
        echo "<pre>";
    }

    $dbh = null;
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}

?>