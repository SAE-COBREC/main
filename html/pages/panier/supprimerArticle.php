<?php
    include '../../selectBDD.php';
    session_start();
    if (isset($_SESSION['idClient'])) {//regarde si le client est connecté
        $id_client = $_SESSION['idClient'];

        if (isset($_POST['id_produit'])) {
            $id_produit = intval($_POST['id_produit']); //converti en entier
            
            $pdo->exec("SET search_path TO cobrec1");
            
            //requete pour supprimer l'article du panier
            $requeteSuppression = "DELETE FROM _contient WHERE id_produit = " . $id_produit . " AND id_panier = " . $_SESSION['panierEnCours'];
            $stmt = $pdo->prepare($requeteSuppression);
            $stmt->execute();
        }
    } else { //si pas connecté
        $id_produit = intval($_POST['id_produit']);
        unset($_SESSION['panierTemp'][$id_produit]); //retirer du panier visiteur
    }

    //redirection vers la page du panier
    header('Location: index.php');
    exit();
?>