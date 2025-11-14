<?php
    include '../../selectBDD.php';
    session_start();
    $id_client = $_SESSION['id'];
    
    $pdo->exec("SET search_path TO cobrec1");
    
    //requete pour vider le panier
    $requeteSuppression = "DELETE FROM _contient WHERE id_panier = :id_panier";
    $stmt = $pdo->prepare($requeteSuppression);
    $stmt->execute([':id_panier' => $_SESSION['panierEnCours']]);


    //redirection vers la page du panier
    header('Location: index.php');
    exit();
?>