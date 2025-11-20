<?php
    include '../../selectBDD.php';
    session_start();
    if (isset($_SESSION['idClient'])){//vérifie que le client soit connécté
        $id_client = $_SESSION['idClient'];
        
        $pdo->exec("SET search_path TO cobrec1");
        
        //requete pour vider le panier
        $requeteSuppression = "DELETE FROM _contient WHERE id_panier = :id_panier";
        $stmt = $pdo->prepare($requeteSuppression);
        $stmt->execute([':id_panier' => $_SESSION['panierEnCours']]);
    } else { //sinon
        $_SESSION['panierTemp'] = array(); //mettre son panier à vide ave une simple liste vide
    }



    //redirection vers la page du panier
    header('Location: index.php');
    exit();
?>