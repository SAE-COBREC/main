<?php
include '../../../config.php';

if (isset($_POST['id_produit']) && isset($_POST['id_panier'])) {
    $id_produit = intval($_POST['id_produit']); //onverti en entier
    $id_panier = intval($_POST['id_panier']); //onverti en entier
    
    $pdo->exec("SET search_path TO cobrec1");
    
    //requete pour supprimer l'article du panier
    $requeteSuppression = "DELETE FROM _contient WHERE id_produit = :id_produit AND id_panier = :id_panier";
    $stmt = $pdo->prepare($requeteSuppression);
    $stmt->execute([
        ':id_produit' => $id_produit, //ermplace id_produit par la varibale lors de l'éxectution
        ':id_panier' => $id_panier //ermplace id_produit par la varibale lors de l'éxectution
    ]);
    
    //redirection vers la page du panier
    header('Location: index.php');
    exit();
}
?>