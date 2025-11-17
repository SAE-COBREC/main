<?php
    session_start();
    include '../../selectBDD.php';

    $pdo->exec("SET search_path TO cobrec1");

    $id_client = $_SESSION['idClient']; //récup l'id du client

    $id_produit = $_POST['id_produit']; //récup l'id du produit pour savoir lequel update
    $quantite = intval($_POST['quantite']); //récupère la quantité en stock pour ne pas la dépasser
    $id_panier = $_SESSION['panierEnCours']; //récup le panier en cours pour savoir dans lequel modifier la quantité


    //cette partie "fonctionne" avec AJAX, le script est envoyer et lancer
    if (!$id_client || !$id_panier) { //si le client existe pas ou que le panier en cours existe pas
        echo json_encode(["success" => false]); //arreter le script et renvoie une erreur
        exit;//arret du script
    }

    $sql = "UPDATE _contient 
            SET quantite = :quantite 
            WHERE id_panier = :id_panier AND id_produit = :id_produit"; //requete pour récup la quantité max en stock

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':quantite' => $quantite,
        ':id_panier' => $id_panier,
        ':id_produit' => $id_produit
    ]);

    echo json_encode(["success" => true]); //requete efféctuer avec scucces
?>
