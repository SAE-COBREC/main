<?php
include '../../selectBDD.php';
    session_start();
    $id_client = $_SESSION['id'];

    if (isset($_POST['id_produit'])) {
        $id_produit = intval($_POST['id_produit']); //onverti en entier
        
        $pdo->exec("SET search_path TO cobrec1");
        
        //requete pour supprimer l'article du panier
        $requeteSuppression = "DELETE FROM _contient WHERE id_produit = :id_produit AND id_panier = (SELECT id_panier FROM _panier_commande WHERE id_client = :id_client);";
        $stmt = $pdo->prepare($requeteSuppression);
        $stmt->execute([
            ':id_produit' => $id_produit, //ermplace id_produit par la varibale lors de l'éxectution
            ':id_client' => $id_client //ermplace id_produit par la varibale lors de l'éxectution
        ]);
        
        //redirection vers la page du panier
        header('Location: index.php');
        exit();
    }
?>