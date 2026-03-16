<?php
session_start(); 

require_once '../../selectBDD.php'; 

header('Content-Type: application/json');

$idClient = $_SESSION['idClient'] ?? null; // récup l'id client

$idProduit = $_GET['idProduit'] ?? null; //récup l'id produit

$dernierePage = $_GET['page'] ?? null; //récup la page sur laquelle était le client 
if (!$idClient) {                                                      //si il n'y a pas d'id client
    echo json_encode(['succes' => false, 'error' => 'not_logged_in']); //renvoie une erreur not_logged_in
    exit;
}

//fonction qui permet d'ajouté au favoris
function ajouterAuxFavoris($pdo, $idClient, $idProduit) {
    $query = "INSERT INTO cobrec1._favoris (id_client, id_produit) VALUES (:id_client, :id_produit)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute(['id_client' => $idClient, 'id_produit' => $idProduit]);
}

//fonction qui permet de supprimer des favoris
function supprimerDesFavoris($pdo, $idClient, $idProduit) {
    $query = "DELETE FROM cobrec1._favoris WHERE id_client = :id_client AND id_produit = :id_produit";
    $stmt = $pdo->prepare($query);
    return $stmt->execute(['id_client' => $idClient, 'id_produit' => $idProduit]);
}


if ($idProduit) { //si l'id du produit est donnée
    $check = $pdo->prepare("SELECT 1 FROM cobrec1._favoris WHERE id_client = ? AND id_produit = ?"); //on regarder si il le client à déjà dans ses favoris
    $check->execute([$idClient, $idProduit]);                                                        //on execute
    if ($check->fetch()) {                                           //si il est déjà dans les favoris
        $res = supprimerDesFavoris($pdo, $idClient, $idProduit);     //on supprime des favoris
        echo json_encode(['succes' => $res, 'action' => 'retirer']); //on renvoie retirer
    } else {                                                         //sinon
        $res = ajouterAuxFavoris($pdo, $idClient, $idProduit);       //on ajoute au favoris
        echo json_encode(['succes' => $res, 'action' => 'ajoute']);  //on renvoie ajoute
    }
} else {                                                               
    echo json_encode(['succes' => false, 'error' => 'pas d\'id arrivée']); //pa d'id
}