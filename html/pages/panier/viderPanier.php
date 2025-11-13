<?php
include '../../selectBDD.php';

if (isset($_POST['id_panier_a_vider'])) {
    $id_panier_a_vider = intval($_POST['id_panier_a_vider']);
    $pdo->exec("SET search_path TO cobrec1");
    
    //requete pour vider le panier
    $requeteSuppression = "DELETE FROM _contient WHERE id_panier = :id_panier";
    $stmt = $pdo->prepare($requeteSuppression);
    $stmt->execute([
        ':id_panier' => $id_panier_a_vider 
    ]);
    echo "ok";
    //redirection vers la page du panier
    header('Location: index.php');
    echo "ok";
    exit();
}
?>