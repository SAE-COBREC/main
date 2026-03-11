<?php
    session_start();
    include '../../selectBDD.php';
    include '../fonctions.php';

    if (isset($_SESSION['idClient'])){ //si le client est connecté
        $pdo->exec("SET search_path TO cobrec1");

        //récup des informations nécessaires à la mise à jour de la quantité
        $id_client = $_SESSION['idClient'];
        $idPanier = $_SESSION['panierEnCours'];
        $idProduit = $_GET['idProduit'];
        if (!$id_client || !$idPanier) { //si l'une des deux valeurs n'est pas renseigné alors erreur;
            echo json_encode(["success" => false]);
            exit;
        }
        $resultat = ajouterArticleBDD($pdo, $idProduit, $idPanier, 1);

        echo json_encode($resultat);
    } else {
        echo json_encode(["success" => $false, "message" => "non connecté"]); //renvoie de l'erreur dans la console si il y en a une
    }
    exit;
?>
