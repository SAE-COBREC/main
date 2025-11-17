<?php
    session_start();
    include '../../selectBDD.php';

    $pdo->exec("SET search_path TO cobrec1");

    $id_client = $_SESSION['idClient'];

    $id_produit = $_POST['id_produit'];
    $quantite = intval($_POST['quantite']);
    $id_panier = $_SESSION['panierEnCours'];

    if (!$id_client || !$id_panier) {
        echo json_encode(["success" => false]);
        exit;
    }

    $sql = "UPDATE _contient 
            SET quantite = :quantite 
            WHERE id_panier = :id_panier AND id_produit = :id_produit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':quantite' => $quantite,
        ':id_panier' => $id_panier,
        ':id_produit' => $id_produit
    ]);

    echo json_encode(["success" => true]);
?>
