<?php
    include '../../selectBDD.php';
    session_start();
    $id_client = $_SESSION['id'];

    if (isset($_POST['id_produit'])) {
        $id_produit = intval($_POST['id_produit']); //converti en entier
        
        $pdo->exec("SET search_path TO cobrec1");
        
        //requete pour supprimer l'article du panier
        $requeteSuppression = "DELETE FROM _contient WHERE id_produit = " . $id_produit . " AND id_panier = " . $_SESSION['panierEnCours'];
        $stmt = $pdo->prepare($requeteSuppression);
        $stmt->execute();
        
        //redirection vers la page du panier
        header('Location: index.php');
        exit();
    }
?>