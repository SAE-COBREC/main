<?php
    include '../../selectBDD.php';
    session_start();
    if (isset($_SESSION['idClient'])){//vérifie que le client soit connécté
        $id_client = $_SESSION['idClient'];
        
        $pdo->exec("SET search_path TO cobrec1");
        
        //requete pour vider les favoris
        $requeteSuppression = "DELETE FROM _favoris WHERE id_client = :id_client";
        $stmt = $pdo->prepare($requeteSuppression);
        $stmt->execute([':id_client' => $id_client]);
    }


    //redirection vers la page du panier
    header('Location: index.php');
    exit();
?>