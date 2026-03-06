<?php
session_start();
include '../../../selectBDD.php';
include '../../fonctions.php';
header('Content-Type: application/json');

$vendeur_id = $_SESSION['vendeur_id'];

try {
    // On récupère toutes les commandes via ta fonction d'origine
    $commandes = recupInfoPourStatsGeneral($pdo, $vendeur_id);

    //on créé un tableau de taille 12 rempli de 0 pour l'évolution
    $evolArticleParMois = array_fill(1, 12, NULL);

    $modeChoisi = $_GET['modeAffichage'];
    $type = $_GET['type'] ?? 'montant';
    $articleCible = $_GET['article']; // L'article choisi dans le filtre

    if ($modeChoisi !== "annee"){
        $debutPeriode = new DateTime($_GET['debut']);
        $finPeriode = (new DateTime($_GET['fin']))->setTime(23, 59, 59); 
    } 

    foreach ($commandes as $articleCommande){
        // On ne traite que si c'est l'article sélectionné
        if ($articleCommande['p_nom'] === $articleCible && !empty($articleCommande['date'])) {
            $date = new DateTime($articleCommande['date']);
            $estValide = false;   
            $mois = (int)$date->format('n'); 

            if ($modeChoisi == "annee"){
                $anneeChoisi = $_GET['annee'] ?? date('Y');
                if ($date->format('Y') == $anneeChoisi) {
                    $estValide = true;
                }
            } else {
                if ($debutPeriode <= $date && $finPeriode >= $date) {
                    $estValide = true;
                }
            }

            if ($estValide) {
                // On suit le type (montant ou volume) choisi par l'utilisateur
                if ($type === "montant") {
                    $evolArticleParMois[$mois] += ($articleCommande['p_prix'] * $articleCommande['quantite']);
                } else {
                    // Pour nbArticle ou nbCommande, on compte ici la quantité vendue de cet article
                    $evolArticleParMois[$mois] += $articleCommande['quantite'];
                }
            }
        }
    }

    echo json_encode(["graph3" => array_values($evolArticleParMois)]);
    exit;

} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}