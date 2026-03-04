<?php
session_start();
include '../../../selectBDD.php';
include '../../fonctions.php';
header('Content-Type: application/json');

$vendeur_id = $_SESSION['vendeur_id'];

try {
    $commandes = recupInfoPourStatsGeneral($pdo, $vendeur_id);


    //================================================
    //PREMIER GRAPHIQUE
    //================================================
    //on créé un tableau de taille 12 rempli de 0
    $donneesParMois = array_fill(1, 12, 0);

    //on créé un tableau vide pour savoir si la commande est déjà compté
    $panierTraites = array();

    
    $modeChoisi = $_GET['modeAffichage'];
    $type = $_GET['type'] ?? 'montant';
    foreach ($commandes as $articleCommande){                          //pour chaque article de toutes les commandes
        if (!empty($articleCommande['date'])) {                        //si la date n'est pas vide
            $date = new DateTime($articleCommande['date']);                      
            $mois = $date->format('n'); //on récupère le numéro du mois pour push dans le bonne indice du tableau (janvier indice 1, février indice 2...)

            //------------------------------------DEMANDE SI ON DOIT METTRE LA TVA ET LES FRAIS DE PORTS DANS LES STATS ------------------------------------
            if ($modeChoisi == "annee"){
                $anneeChoisi = $_GET['annee'] ?? date('Y'); 
                if ($date->format('Y') == $anneeChoisi){ //si la date correspond à l'année choisi
                    switch ($type){
                        case "montant":
                            $totalArticleCommande = $articleCommande['p_prix'] * $articleCommande['quantite']; //on calcul le prix total des articles en fonction de la quantité
                            $donneesParMois[$mois] += $totalArticleCommande;       
                            break;

                        case "nbArticle":
                            $donneesParMois[$mois] += $articleCommande['quantite'];
                            break;

                        case "nbCommande":
                            if (!in_array($articleCommande['id_panier'], $panierTraites)){
                                array_push($panierTraites, $articleCommande['id_panier']);
                                $donneesParMois[$mois] += 1;
                            }
                            break;
                    }
                }
            } else {
                $debutPeriode = new DateTime($_GET['debut']);
                $finPeriode = new DateTime($_GET['fin']);
                if ($debutPeriode <= $date && $finPeriode >= $date){
                    switch ($type){
                        case "montant":
                            $totalArticleCommande = $articleCommande['p_prix'] * $articleCommande['quantite']; //on calcul le prix total des articles en fonction de la quantité
                            $donneesParMois[$mois] += $totalArticleCommande;       
                            break;

                        case "nbArticle":
                            $donneesParMois[$mois] += $articleCommande['quantite'];
                            break;

                        case "nbCommande":
                            if (!in_array($articleCommande['id_panier'], $panierTraites)){
                                array_push($panierTraites, $articleCommande['id_panier']);
                                $donneesParMois[$mois] += 1;
                            }
                            break;
                    }
                }
            }
        }
        
    }
    $donneesGraphique1 = array_values($donneesParMois); //on converti le tableau pour qu'il soit au bon format pour le graphique

    //================================================
    //DEUXIEME GRAPHIQUE
    //================================================

    echo json_encode($donneesGraphique1);
    exit;

} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}
?>