<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur pour accéder aux données de connexion
session_start();

//charge le fichier de connexion à la base de données
include '../../selectBDD.php';

//charge le fichier contenant toutes les fonctions personnalisées
include '../fonctions.php';

//définit le type de contenu de la réponse en JSON pour l'appel AJAX
header('Content-Type: application/json');

// ============================================
// VÉRIFICATION DE LA SESSION CLIENT
// ============================================

//vérifie que le client est bien connecté avant de retourner des données
if (!isset($_SESSION['idClient'])) {
    //renvoie une erreur JSON si le client n'est pas connecté
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

//récupère et sécurise l'identifiant du client depuis la session
$identifiantClientConnecte = (int) $_SESSION['idClient'];

try {

    // ============================================
    // REQUÊTE PRINCIPALE : DONNÉES DE COMMANDES
    // ============================================

    //prépare la requête SQL pour récupérer tous les articles commandés par ce client
    //avec leurs informations de produit, catégorie et facture associée
    $requeteSQLCommandesClient = "
        SELECT
            pc.id_panier,
            pc.timestamp_commande     AS date,
            c.quantite,
            p.p_nom,
            p.p_prix,
            cp.nom_categorie,
            COALESCE(f.f_total_ttc, 0) AS montant_total_commande
        FROM cobrec1._panier_commande pc
        LEFT JOIN cobrec1._contient c          ON pc.id_panier    = c.id_panier
        LEFT JOIN cobrec1._produit p           ON c.id_produit    = p.id_produit
        LEFT JOIN cobrec1._fait_partie_de fpd  ON p.id_produit    = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON cp.id_categorie = fpd.id_categorie
        LEFT JOIN cobrec1._facture f           ON pc.id_panier    = f.id_panier
        WHERE pc.id_client = :id_client
          AND pc.timestamp_commande IS NOT NULL
          AND p.p_nom IS NOT NULL
    ";

    //exécute la requête avec l'identifiant du client
    $requetePrepareeCommandes = $pdo->prepare($requeteSQLCommandesClient);
    $requetePrepareeCommandes->execute(['id_client' => $identifiantClientConnecte]);

    //récupère toutes les lignes de résultats
    $lignesResultatCommandes = $requetePrepareeCommandes->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // LECTURE DES PARAMÈTRES DE FILTRES
    // ============================================

    //récupère le mode d'affichage choisi par le client (par année ou par période)
    $modeAffichageChoisi = $_GET['modeAffichage'] ?? 'annee';

    //récupère le type de valeur à afficher (montant, nombre d'articles ou de commandes)
    $typeAffichageChoisi = $_GET['type'] ?? 'montant';

    //récupère le filtre de catégorie sélectionné
    $categorieSelectionnee = $_GET['categorie'] ?? 'toutes';

    //initialise les bornes de la période à null
    $dateDebutPeriode = null;
    $dateFinPeriode   = null;

    //détermine les bornes temporelles selon le mode d'affichage
    if ($modeAffichageChoisi !== 'annee') {
        //vérifie que les dates de début et de fin sont bien fournies
        if (empty($_GET['debut']) || empty($_GET['fin'])) {
            //renvoie une erreur si la période est incomplète
            echo json_encode(['error' => 'Période incomplète']);
            exit;
        }
        //crée les objets DateTime pour les bornes de la période
        $dateDebutPeriode = new DateTime($_GET['debut']);
        //fixe l'heure de fin à 23:59:59 pour inclure toute la journée finale
        $dateFinPeriode = (new DateTime($_GET['fin']))->setTime(23, 59, 59);
    } else {
        //en mode annuel, récupère l'année choisie (année courante par défaut)
        $anneeChoisie = $_GET['annee'] ?? date('Y');
    }

    // ============================================
    // INITIALISATION DES TABLEAUX DE RÉSULTATS
    // ============================================

    //initialise le tableau des données mensuelles pour le graphique d'évolution (graph 1)
    $donneesEvolMensuelle = array_fill(1, 12, 0);

    //initialise le tableau des données pour le top produits (graph 2)
    $tableauTopProduits = [];

    //initialise les tableaux pour la répartition par catégorie (graph 3)
    $tableauDonneesCategories = [];

    //initialise le tableau de suivi des paniers déjà traités (pour éviter les doublons)
    $identifiantsPaniersTraites = [];

    // ============================================
    // INITIALISATION DES INDICATEURS KPI
    // ============================================

    //initialise le montant total dépensé sur la période
    $montantTotalDepense = 0;

    //initialise le compteur de commandes passées
    $nombreTotalCommandes = 0;

    //initialise le compteur d'articles achetés
    $nombreTotalArticles = 0;

    //initialise le nom du produit le plus commandé
    $nomTopProduit = '';

    //initialise le tableau pour compter les quantités par produit
    $tableauQuantiteParProduit = [];

    // ============================================
    // TRAITEMENT LIGNE PAR LIGNE DES COMMANDES
    // ============================================

    foreach ($lignesResultatCommandes as $ligneCommande) {

        //ignore les lignes sans date de commande
        if (empty($ligneCommande['date'])) continue;

        //crée l'objet DateTime à partir de la date de la commande
        $dateCommande = new DateTime($ligneCommande['date']);

        //extrait le numéro du mois (1 à 12) pour l'indexation mensuelle
        $numeroMois = (int) $dateCommande->format('n');

        // ── Filtre période / année ──

        //vérifie si la commande appartient à la période sélectionnée
        $commandeAppartientPeriode = false;
        if ($modeAffichageChoisi === 'annee') {
            //compare l'année de la commande avec l'année choisie
            if ($dateCommande->format('Y') == $anneeChoisie) $commandeAppartientPeriode = true;
        } else {
            //vérifie que la date est dans l'intervalle de la période choisie
            if ($dateDebutPeriode <= $dateCommande && $dateFinPeriode >= $dateCommande) $commandeAppartientPeriode = true;
        }

        //passe à la ligne suivante si la commande est hors période
        if (!$commandeAppartientPeriode) continue;

        // ── Filtre catégorie ──

        //filtre par catégorie si une catégorie spécifique est sélectionnée
        if ($categorieSelectionnee !== 'toutes' && $categorieSelectionnee !== $ligneCommande['nom_categorie']) continue;

        //récupère et type les données utiles de la ligne courante
        $quantiteArticle      = (int)   $ligneCommande['quantite'];
        $prixUnitaireProduit  = (float) $ligneCommande['p_prix'];
        $nomProduit           = $ligneCommande['p_nom'];
        $nomCategorieProduit  = $ligneCommande['nom_categorie'] ?? 'Non classé';

        // ── Alimentation du graphique 1 : évolution mensuelle ──

        //cumule la valeur dans le bon mois selon le type d'affichage choisi
        switch ($typeAffichageChoisi) {
            case 'montant':
                //calcule le montant de la ligne et l'ajoute au mois correspondant
                $valeurLigneMontant = $prixUnitaireProduit * $quantiteArticle;
                $donneesEvolMensuelle[$numeroMois] += $valeurLigneMontant;
                break;
            case 'nbArticle':
                //ajoute la quantité d'articles au mois correspondant
                $donneesEvolMensuelle[$numeroMois] += $quantiteArticle;
                break;
            case 'nbCommande':
                //compte chaque commande unique une seule fois par mois
                if (!in_array($ligneCommande['id_panier'], $identifiantsPaniersTraites)) {
                    $identifiantsPaniersTraites[]       = $ligneCommande['id_panier'];
                    $donneesEvolMensuelle[$numeroMois] += 1;
                }
                break;
        }

        // ── Alimentation du graphique 2 : top produits ──

        //cumule les valeurs par produit sauf en mode nombre de commandes
        if ($typeAffichageChoisi !== 'nbCommande') {
            //calcule la valeur à ajouter pour ce produit selon le type
            $valeurPourTopProduit = ($typeAffichageChoisi === 'montant') ? ($prixUnitaireProduit * $quantiteArticle) : $quantiteArticle;
            if (!isset($tableauTopProduits[$nomProduit])) $tableauTopProduits[$nomProduit] = 0;
            $tableauTopProduits[$nomProduit] += $valeurPourTopProduit;
        }

        // ── Alimentation du graphique 3 : répartition par catégorie ──

        //cumule les valeurs par catégorie sauf en mode nombre de commandes
        if ($typeAffichageChoisi !== 'nbCommande') {
            //calcule la valeur à ajouter pour cette catégorie selon le type
            $valeurPourCategorie = ($typeAffichageChoisi === 'montant') ? ($prixUnitaireProduit * $quantiteArticle) : $quantiteArticle;
            if (!isset($tableauDonneesCategories[$nomCategorieProduit])) $tableauDonneesCategories[$nomCategorieProduit] = 0;
            $tableauDonneesCategories[$nomCategorieProduit] += $valeurPourCategorie;
        }

        // ── Mise à jour des KPIs ──

        //incrémente le nombre total d'articles
        $nombreTotalArticles += $quantiteArticle;

        //enregistre l'identifiant du panier pour le dédoublonnage (hors mode nbCommande)
        if (!in_array($ligneCommande['id_panier'], $identifiantsPaniersTraites) && $typeAffichageChoisi !== 'nbCommande') {
            $identifiantsPaniersTraites[] = $ligneCommande['id_panier'];
        }

        //ajoute le montant de la ligne au total dépensé
        $montantTotalDepense += $prixUnitaireProduit * $quantiteArticle;

        //accumule les quantités par produit pour déterminer le top produit
        if (!isset($tableauQuantiteParProduit[$nomProduit])) $tableauQuantiteParProduit[$nomProduit] = 0;
        $tableauQuantiteParProduit[$nomProduit] += $quantiteArticle;
    }

    // ============================================
    // CALCUL DES INDICATEURS FINAUX
    // ============================================

    //calcule le nombre de commandes uniques à partir des paniers dédoublonnés
    $nombreTotalCommandes = count(array_unique($identifiantsPaniersTraites));

    //détermine le produit le plus commandé en triant par quantité décroissante
    if (!empty($tableauQuantiteParProduit)) {
        arsort($tableauQuantiteParProduit);
        $nomTopProduit = array_key_first($tableauQuantiteParProduit);
    }

    //calcule le panier moyen en divisant le total dépensé par le nombre de commandes
    $montantMoyenParCommande = ($nombreTotalCommandes > 0) ? round($montantTotalDepense / $nombreTotalCommandes, 2) : 0;

    //trie les produits du top par valeur décroissante et limite à 10 résultats
    arsort($tableauTopProduits);
    $tableauTopProduits = array_slice($tableauTopProduits, 0, 10, true);

    // ============================================
    // CONSTRUCTION ET ENVOI DE LA RÉPONSE JSON
    // ============================================

    //construit le tableau de réponse structuré pour les 3 graphiques et les KPIs
    $reponseJson = [
        //données du graphique 1 : évolution mensuelle
        'graph1' => [
            'data' => array_values($donneesEvolMensuelle),
        ],
        //données du graphique 2 : top 10 produits
        'graph2' => [
            'labels' => array_keys($tableauTopProduits),
            'data'   => array_values($tableauTopProduits),
        ],
        //données du graphique 3 : répartition par catégorie
        'graph3' => [
            'labels' => array_keys($tableauDonneesCategories),
            'data'   => array_values($tableauDonneesCategories),
        ],
        //indicateurs clés de performance
        'kpi' => [
            'totalDepense'    => round($montantTotalDepense, 2),
            'totalCommandes'  => $nombreTotalCommandes,
            'totalArticles'   => $nombreTotalArticles,
            'topProduit'      => $nomTopProduit,
            'moyenneCommande' => $montantMoyenParCommande,
        ],
    ];

    //encode et envoie la réponse en JSON
    echo json_encode($reponseJson);
    exit;

} catch (PDOException $e) {
    //capture les erreurs de base de données et les renvoie en JSON sécurisé
    echo json_encode(['error' => htmlspecialchars($e->getMessage())]);
    exit;
}