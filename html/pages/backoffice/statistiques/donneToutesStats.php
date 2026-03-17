<?php
session_start();
include '../../../selectBDD.php';
include '../../fonctions.php';
header('Content-Type: application/json');

$vendeur_id = $_SESSION['vendeur_id'];
unlink("test.txt");
try {
    $commandes = recupInfoPourStatsGeneral($pdo, $vendeur_id);

    //on créé un tableau de taille 12 rempli de 0
    $donneesParMois = array_fill(1, 12, 0);

    //on créé un tableau vide pour savoir si la commande est déjà compté
    $panierTraites = array();

    //on crée un tableau vude pour savoir si le produit est déjà compté
    $estDejaLabel = array();
    
    //on crée un tableau vude pour compté par produit ce tableau est lié avec $estDejaLabel
    $compteParLabel = array();

    $modeChoisi = $_GET['modeAffichage'];
    $type = $_GET['type'] ?? 'montant';
    $categoriesChoisi = $_GET['categorie'];

    if ($modeChoisi !== "annee"){
        $debutPeriode = new DateTime($_GET['debut']);
        $finPeriode = (new DateTime($_GET['fin']))->setTime(23, 59, 59); //permet de set l'heure sur 26h59m59s pour que la jouréne soit compté
    } 

    foreach ($commandes as $articleCommande){                          //pour chaque article de toutes les commandes
        if (!empty($articleCommande['date'])) {                        //si la date n'est pas vide
            $date = new DateTime($articleCommande['date']);            //on récup la date d'achat
            $estValide = false;   
            $categorievalide = false;                                     
            $mois = $date->format('n'); //on récupère le numéro du mois pour push dans le bonne indice du tableau (janvier indice 1, février indice 2...)

            if ($modeChoisi == "annee"){                    //si le mode est annee
                $anneeChoisi = $_GET['annee'] ?? date('Y'); //on récup l'année choisi ou celle actuelle
                if ($date->format('Y') == $anneeChoisi){    //si la date correspond à l'année choisi
                    $estValide = true;                      //on passe le booleen à true
                }
            } else {                                        //sinon
                if ($debutPeriode <= $date && $finPeriode >= $date) { //si la date est comprise entre
                    $estValide = true;
                }
            }
            if ($modeChoisi == "annee"){
                $anneeChoisi = $_GET['annee'] ?? date('Y'); 
                if ($date->format('Y') == $anneeChoisi){ //si la date correspond à l'année choisi
                    
                }
            }
            
            if ($categoriesChoisi == "toutes"){
                $categorievalide = true;
            }
            else if ($categoriesChoisi == $articleCommande['nom_categorie']){
                $categorievalide = true;
            } else {
                $categorievalide = false;
            }

            if ($estValide && $categorievalide){
                switch ($type){

                    //=============
                    //MONTANT
                    //=============
                        case "montant":
                            $totalArticleCommande = $articleCommande['p_prix'] * $articleCommande['quantite']; //on calcul le prix total des articles en fonction de la quantité
                            $donneesParMois[$mois] += $totalArticleCommande;    
                            if (!in_array($articleCommande['p_nom'], $estDejaLabel)){
                                array_push($estDejaLabel, $articleCommande['p_nom']);
                                array_push($compteParLabel, $articleCommande['quantite'] * $articleCommande['p_prix']);
                            } else {
                                $indiceProduit = array_search($articleCommande['p_nom'], $estDejaLabel);
                                $compteParLabel[$indiceProduit] += $articleCommande['quantite'] * $articleCommande['p_prix'];
                            } 
                            break;
                        
                    //================
                    //NOMBRE D'ARTICLE
                    //================
                        case "nbArticle":
                            $donneesParMois[$mois] += $articleCommande['quantite'];
                            if (!in_array($articleCommande['p_nom'], $estDejaLabel)){
                                array_push($estDejaLabel, $articleCommande['p_nom']);
                                array_push($compteParLabel, $articleCommande['quantite']);
                                $indiceProduit = array_search($articleCommande['p_nom'], $estDejaLabel);
                            } else {
                                $indiceProduit = array_search($articleCommande['p_nom'], $estDejaLabel);
                                $compteParLabel[$indiceProduit] += $articleCommande['quantite'];
                            } 
                            break;

                    //==================
                    //NOMBRE DE COMMANDE
                    //==================
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
    $reponse = [
        "graph1" => array_values($donneesParMois), //on converti le tableau pour qu'il soit au bon format pour le graphique
        "graph2" => [
            "labels" => array_values($estDejaLabel), //on converti le tableau pour qu'il soit au bon format pour le graphique
            "data" => array_values($compteParLabel) //on converti le tableau pour qu'il soit au bon format pour le graphique
        ]
    ];
    echo json_encode($reponse);                       
    exit;

} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}
?>