<?php
include(__DIR__ . '/../../selectBDD.php');

$pdo->exec("SET search_path TO cobrec1");

$id_produit = $_POST['id_produit'] ?? null;
$id_panier = 8;

if ($id_produit) {
    try {
        $requeteVerif = "SELECT c_quantite FROM _contient WHERE id_panier = :id_panier AND id_produit = :id_produit";
        
        $stmtVerif = $pdo->prepare($requeteVerif);
        $stmtVerif->execute([
            ':id_panier' => $id_panier,
            ':id_produit' => $id_produit
        ]);
        
        $existe = $stmtVerif->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $nouvelleQuantite = $existe['c_quantite'] + 1;
            
            $requeteUpdate = "UPDATE _contient SET c_quantite = :quantite WHERE id_panier = :id_panier AND id_produit = :id_produit";
            
            $stmtUpdate = $pdo->prepare($requeteUpdate);
            $stmtUpdate->execute([
                ':quantite' => $nouvelleQuantite,
                ':id_panier' => $id_panier,
                ':id_produit' => $id_produit
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Produit ' . $id_produit . ' ajouté au panier !']);
            
        } else {
            $requeteInsert = "INSERT INTO _contient (id_panier, id_produit, c_quantite) VALUES (:id_panier, :id_produit, 1)";
            
            $stmtInsert = $pdo->prepare($requeteInsert);
            $stmtInsert->execute([
                ':id_panier' => $id_panier,
                ':id_produit' => $id_produit
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Produit ' . $id_produit . ' ajouté au panier !']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
}
?>
