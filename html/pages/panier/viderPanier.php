<?php
include '../../selectBDD.php';

    include '../../config.php';
    session_start();
    $id_client = $_SESSION['id'];
    $pdo->exec("SET search_path TO cobrec1");

    //requete pour vider le panier
    $requeteSuppression = "DELETE FROM _contient WHERE id_panier = (SELECT id_panier FROM _panier_commande WHERE id_client = :id_client)";
    $stmt = $pdo->prepare($requeteSuppression);
    $stmt->execute([':id_client' => $id_client]);


    //redirection vers la page du panier
    header('Location: index.php');
    exit();
?>