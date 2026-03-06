<?php
session_start();
include '../../../selectBDD.php'; 
header('Content-Type: application/json');

if(empty($_SESSION['vendeur_id']) || empty($_GET['article'])) {
    echo json_encode(['graph3' => array_fill(0, 12, 0)]);
    exit();
}

$vendeur_id = $_SESSION['vendeur_id'];
$articleCible = $_GET['article'];
$mode = $_GET['modeAffichage'] ?? 'annee';
$type = $_GET['type'] ?? 'montant';

try {
    // On ne sélectionne que ce qui est nécessaire pour l'article précis
    $sql = "SELECT p_prix, quantite, date 
            FROM vue_details_commandes 
            WHERE vendeur_id = :vendeur_id 
            AND p_nom = :article";
    
    $params = [
        ':vendeur_id' => $vendeur_id,
        ':article' => $articleCible
    ];

    // Filtre temporel
    if ($mode === 'annee') {
        $sql .= " AND YEAR(date) = :annee";
        $params[':annee'] = $_GET['annee'] ?? date('Y');
    } else {
        $sql .= " AND date BETWEEN :debut AND :fin";
        $params[':debut'] = $_GET['debut'] . " 00:00:00";
        $params[':fin'] = $_GET['fin'] . " 23:59:59";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation des 12 mois à 0
    $donneesMensuelles = array_fill(1, 12, 0);

    foreach ($lignes as $row) {
        $mois = (int)date('n', strtotime($row['date']));
        $valeur = ($type === 'montant') ? ($row['p_prix'] * $row['quantite']) : (int)$row['quantite'];
        $donneesMensuelles[$mois] += $valeur;
    }

    echo json_encode(["graph3" => array_values($donneesMensuelles)]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}