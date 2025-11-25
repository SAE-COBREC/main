<?php
    session_start();
    include '../../selectBDD.php';
    if (isset($_SESSION['idClient'])){ //si le client est connecté
        $pdo->exec("SET search_path TO cobrec1");

        //récup des informations nécessaires à la mise à jour de la quantité
        $id_client = $_SESSION['idClient'];
        $id_produit = $_POST['id_produit'];
        $quantite = intval($_POST['quantite']);
        $id_panier = $_SESSION['panierEnCours'];

        if (!$id_client || !$id_panier) { //si l'une des deux valeurs n'est pas renseigné alors erreur;
            echo json_encode(["success" => false]);
            exit;
        }

        $sql = "UPDATE _contient 
                SET quantite = :quantite 
                WHERE id_panier = :id_panier AND id_produit = :id_produit"; //requête pour update la quantite dans le panier

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':quantite' => $quantite,
            ':id_panier' => $id_panier,
            ':id_produit' => $id_produit
        ]);//execute la quantité

        echo json_encode(["success" => $result]); //renvoie de l'erreur dans la console si il y en a une
    } else {//sinon
        $id_produit = intval($_POST['id_produit']); //récup l'id du produit 
        $quantite = intval($_POST['quantite']); //récup la nouvelle quantité
        $_SESSION['panierTemp'][$id_produit]['quantite'] = $quantite;//met à jour la quantité dans le panier temporaire

        echo json_encode(["success" => true]);//renvoie le succès de la requête
    }

?>
