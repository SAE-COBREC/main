<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// GESTION DE LA SESSION ET DU PANIER
// ============================================

//récupère l'ID du client s'il est connecté
$idClient = $_SESSION['idClient'] ?? null;
//initialise l'ID du panier
$idPanier = null;

//vérifie si le client est connecté ou pas
if ($idClient === null) {
    //client non connecté : utilise un panier temporaire
    if (!isset($_SESSION['panierTemp'])) {
        //crée un panier temporaire vide
        $_SESSION['panierTemp'] = [];
    }
} else {
    //client connecté : cherche son panier dans la base
    
    //prépare la requête pour trouver le panier en cours
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL AND id_client = :idClient
    ";
    
    //exécute la requête pour trouver le panier
    $stmtPanier = $connexionBaseDeDonnees->prepare($sqlPanierClient);
    $stmtPanier->execute([':idClient' => $idClient]);
    //récupère le résultat
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    //vérifie si un panier existe
    if ($panier) {
        //récupère l'ID du panier existant
        $idPanier = (int) $panier['id_panier'];
    } else {
        //aucun panier trouvé : crée un nouveau panier
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";
        
        //insère le nouveau panier et récupère son ID
        $stmtCreate = $connexionBaseDeDonnees->prepare($sqlCreatePanier);
        $stmtCreate->execute([':idClient' => $idClient]);
        $idPanier = (int) $stmtCreate->fetchColumn();
    }

    //sauvegarde l'ID du panier dans la session
    $_SESSION['panierEnCours'] = $idPanier;
    //transfère les produits du panier temporaire vers la base
    transfererPanierTempVersBDD($connexionBaseDeDonnees, $idPanier);
}

// ============================================
// TRAITEMENT AJAX : AJOUT AU PANIER
// ============================================

//vérifie si c'est une requête d'ajout au panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    //définit le type de réponse en JSON
    header('Content-Type: application/json');

    //récupère l'ID du produit à ajouter
    $idProduit = $_POST['idProduit'] ?? null;
    //récupère la quantité demandée
    $quantite = (int) ($_POST['quantite'] ?? 1);

    //vérifie que l'ID produit est présent
    if (!$idProduit) {
        //renvoie une erreur si l'ID est manquant
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
        exit;
    }

    //essaie d'ajouter le produit au panier
    try {
        //vérifie si le client est connecté
        if ($idClient === null) {
            //ajoute le produit au panier temporaire en session
            $resultat = ajouterArticleSession($connexionBaseDeDonnees, $idProduit, $quantite);
        } else {
            //vérifie qu'un panier existe
            if (!$idPanier) {
                //renvoie une erreur si aucun panier n'existe
                echo json_encode(['success' => false, 'message' => 'Aucun panier en cours']);
                exit;
            }
            //ajoute le produit au panier en base de données
            $resultat = ajouterArticleBDD($connexionBaseDeDonnees, $idProduit, $idPanier, $quantite);
        }
        //renvoie le résultat en JSON
        echo json_encode($resultat);
    } catch (Exception $e) {
        //capture les erreurs et les renvoie
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    //arrête le script après traitement
    exit;
}

// ============================================
// CHARGEMENT DES DONNÉES PRODUITS
// ============================================

//charge tous les produits et catégories depuis la base
$donnees = chargerProduitsBDD($connexionBaseDeDonnees);
//extrait la liste des produits
$listeProduits = $donnees['produits'];
//extrait la liste des catégories
$listeCategories = $donnees['categories'];
//compte le nombre total de produits
$totalProduitsSansFiltre = count($listeProduits);

// ============================================
// GESTION DES RECHERCHES
// ============================================

//récupère le terme de recherche pour le vendeur
$rechercheVendeur = trim($_POST['vendeur'] ?? '');
//récupère le terme de recherche pour le nom de produit
$rechercheNom = trim($_POST['nomChercher'] ?? '');

//vérifie si une recherche par vendeur est effectuée
if (!empty($rechercheVendeur)) {
    //filtre les produits par nom de vendeur
    $listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $rechercheVendeur);
    //recompte le nombre de produits
    $totalProduitsSansFiltre = count($listeProduits);
}

//vérifie si une recherche par nom est effectuée
if (!empty($rechercheNom)) {
    try {
        //cherche les produits correspondant au nom
        $listeProduits = chercherProduitsNom($connexionBaseDeDonnees, $rechercheNom);
        //recompte le nombre de produits
        $totalProduitsSansFiltre = count($listeProduits);
    } catch (PDOException $e) {
        //sauvegarde le message d'erreur pour l'afficher
        $messageErreur = htmlspecialchars($e->getMessage());
    }
}

// ============================================
// CALCUL DU PRIX MAXIMUM
// ============================================

//initialise le prix maximum à zéro
$prixMaximum = 0;
//vérifie qu'il y a des produits dans la liste
if (!empty($listeProduits)) {
    //trouve le prix HT le plus élevé parmi tous les produits
    $prixMaximumHT = max(array_column($listeProduits, 'p_prix'));
    
    //parcourt tous les produits pour trouver celui au prix maximum
    foreach ($listeProduits as $produitTmp) {
        //vérifie si c'est le produit au prix maximum
        if ((float) $produitTmp['p_prix'] === (float) $prixMaximumHT) {
            //calcule le prix TTC et l'arrondit
            $prixMaximum = round(calcPrixTVA(
                $produitTmp['tva'],
                $prixMaximumHT
            ));
            //arrête la boucle une fois trouvé
            break;
        }
    }
}

// ============================================
// RÉCUPÉRATION ET APPLICATION DES FILTRES
// ============================================

//récupère la catégorie sélectionnée
$categorieSelection = $_POST['categorie'] ?? 'all';
//récupère le type de tri choisi
$triSelection = $_POST['tri'] ?? 'meilleures_ventes';
//récupère le prix maximum du filtre
$prixMaximumFiltre = isset($_POST['price']) ? (float) $_POST['price'] : $prixMaximum;
//récupère la note minimum du filtre
$noteMinimumFiltre = isset($_POST['note_min']) ? (int) $_POST['note_min'] : 0;
//vérifie si le filtre "en stock" est activé
$enStockSeulement = isset($_POST['stock_only']);

//regroupe tous les filtres dans un tableau
$filtres = [
    'categorieFiltre' => $categorieSelection,
    'noteMinimum' => $noteMinimumFiltre,
    'prixMaximum' => $prixMaximumFiltre,
    'enStockSeulement' => $enStockSeulement
];

//applique les filtres sauf si recherche par nom
if (empty($rechercheNom)) {
    //filtre la liste de produits selon les critères
    $listeProduits = filtrerProduits($listeProduits, $filtres);
}

//trie les produits selon le critère choisi
$listeProduits = trierProduits($listeProduits, $triSelection);

//compte le nombre final de produits après filtres
$tousLesProduits = count($listeProduits);
//prépare les catégories pour l'affichage
$categoriesAffichage = preparercategories_affichage($listeCategories);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>

    <!--charge l'icône du site-->
    <link rel="icon" type="image/png" href="/img/favicon.svg">

    <!--charge les feuilles de style CSS-->
    <link rel="stylesheet" href="/styles/Index/style.css">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
</head>

<body>
    <?php 
    //inclut l'en-tête de la page
    include __DIR__ . '/partials/header.php'; 
    ?>

    <!--conteneur principal de la page-->
    <div class="container">

        <!--barre latérale avec les filtres-->
        <aside>
            <!--formulaire pour soumettre les filtres-->
            <form method="POST" action="" id="filterForm">

                <!--section pour choisir le tri-->
                <div>
                    <span>Tri par :</span>
                    <select name="tri" id="triSelect">
                        <!--option meilleures ventes-->
                        <option value="meilleures_ventes"
                            <?= $triSelection === 'meilleures_ventes' ? 'selected' : '' ?>>Meilleures ventes</option>
                        <!--option prix croissant-->
                        <option value="prix_croissant" <?= $triSelection === 'prix_croissant' ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <!--option prix décroissant-->
                        <option value="prix_decroissant" <?= $triSelection === 'prix_decroissant' ? 'selected' : '' ?>>
                            Prix décroissant</option>
                        <!--option mieux notés-->
                        <option value="note" <?= $triSelection === 'note' ? 'selected' : '' ?>>Mieux notés</option>
                        <!--option en promotion-->
                        <option value="en_promotion" <?= $triSelection === 'en_promotion' ? 'selected' : '' ?>>En
                            promotion</option>
                        <!--option en réduction-->
                        <option value="en_reduction" <?= $triSelection === 'en_reduction' ? 'selected' : '' ?>>En
                            réduction</option>
                    </select>
                </div>

                <!--titre de la section filtres-->
                <div>
                    <h3>Filtres</h3>
                    <!--bouton pour effacer tous les filtres-->
                    <button type="button" id="clearFiltersBtn">Effacer</button>
                </div>

                <!--filtre par catégorie-->
                <section>
                    <h4>Catégories</h4>
                    <select name="categorie" id="categorieSelect">
                        <!--option pour tous les produits-->
                        <option value="all" <?= $categorieSelection === 'all' ? 'selected' : '' ?>>
                            Tous les produits (<?= $totalProduitsSansFiltre ?>)
                        </option>

                        <!--boucle sur toutes les catégories-->
                        <?php foreach ($categoriesAffichage as $categorieCourante): ?>
                        <!--affiche chaque catégorie sauf "all"-->
                        <?php if ($categorieCourante['category'] !== 'all'): ?>
                        <option value="<?= htmlspecialchars($categorieCourante['category']) ?>"
                            <?= $categorieSelection === $categorieCourante['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categorieCourante['category']) ?> (<?= $categorieCourante['count'] ?>)
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </section>

                <!--barre de recherche par vendeur-->
                <div class="search-container" style="justify-content: normal;">
                    <!--icône de loupe-->
                    <img src="/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                    <!--champ de saisie pour le nom du vendeur-->
                    <input type="text" id="searchVendeur" name="vendeur" placeholder="Rechercher vendeur..."
                        class="search-input" value="<?= htmlspecialchars($_POST['vendeur'] ?? '') ?>">
                </div>

                <!--filtre par prix avec curseur-->
                <section class="no-hover">
                    <h4 style="padding-top: 1em;">Prix</h4>
                    <div>
                        <!--curseur pour sélectionner le prix maximum-->
                        <input type="range" id="priceRange" min="0" max="<?= $prixMaximum ?>"
                            value="<?= isset($_POST['price']) ? $_POST['price'] : $prixMaximum ?>">
                    </div>
                    <div>
                        <span>0€</span>
                        <!--champ numérique pour le prix-->
                        <input type="number" name="price" id="priceValue" class="price-input" step="1" min="0"
                            max="<?= $prixMaximum ?>"
                            value="<?= isset($_POST['price']) ? $_POST['price'] : $prixMaximum ?>">
                    </div>
                </section>

                <!--filtre par note minimum-->
                <section>
                    <h4>Note minimum</h4>
                    <!--widget d'étoiles pour sélectionner la note-->
                    <div class="star-rating-filter" id="starFilterWidget">
                        <!--boucle pour créer 5 étoiles cliquables-->
                        <?php for($i=1; $i<=5; $i++): ?>
                        <button type="button" class="star-btn" data-value="<?= $i ?>" aria-label="Note <?= $i ?>">
                            <img src="/img/svg/star-empty.svg" alt="" width="24" height="24">
                        </button>
                        <?php endfor; ?>
                    </div>
                    <!--champ caché pour stocker la note sélectionnée-->
                    <input type="hidden" name="note_min" id="inputNoteMin" value="<?= $noteMinimumFiltre ?>">
                </section>

                <!--filtre par disponibilité-->
                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <!--case à cocher pour afficher seulement les produits en stock-->
                        <input type="checkbox" name="stock_only" id="stockOnlyCheckbox"
                            <?= isset($_POST['stock_only']) ? 'checked' : '' ?>>
                        <span>En stock uniquement</span>
                    </label>
                </section>
            </form>
        </aside>

        <!--zone principale pour afficher les produits-->
        <main>
            <div>
                <!--vérifie s'il y a des produits à afficher-->
                <?php if (empty($listeProduits)): ?>
                <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                <!--boucle sur tous les produits à afficher-->
                <?php foreach ($listeProduits as $produitCourant):
                        //vérifie si le produit est en rupture de stock
                        $estEnRupture = $produitCourant['p_stock'] <= 0;
                        //récupère le pourcentage de réduction
                        $discount = (float)$produitCourant['reduction_pourcentage'];
                        //vérifie s'il y a une réduction
                        $possedePourcentageRemise = $discount > 0;
                        //calcule le prix après réduction
                        $prixDiscount = ($discount > 0) ? $produitCourant['p_prix'] * (1 - $discount/100) : $produitCourant['p_prix'];
                        //calcule le prix final TTC
                        $prixFinal = calcPrixTVA($produitCourant['tva'], $prixDiscount);
                        //calcule le prix original TTC
                        $prixOriginalTTC = calcPrixTVA($produitCourant['tva'], $produitCourant['p_prix']);
                        //arrondit la note moyenne
                        $noteArrondie = round($produitCourant['note_moyenne'] ?? 0);
                        //vérifie si le produit est en promotion
                        $estEnPromotion = !empty($produitCourant['estenpromo']); 
                        //récupère le nom du vendeur
                        $nomVendeur = recupNomVendeurIdProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
                        //récupère l'origine du produit
                        $origineProduit = recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
                        ?>
                <!--carte de produit cliquable-->
                <article
                    class="<?= $estEnRupture ? 'produit-rupture' : '' ?> <?= $estEnPromotion ? 'produit-promotion' : '' ?>"
                    onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">
                    <div>
                        <div>
                            <!--image du produit-->
                            <img src="<?= str_replace("html/img/photo", "/img/photo", htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg')) ?>"
                                alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>"
                                class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                        </div>
                        <!--affiche le badge de réduction s'il y en a une-->
                        <?php if ($possedePourcentageRemise): ?>
                        <span class="badge-reduction">-<?= round($produitCourant['reduction_pourcentage']) ?>%</span>
                        <?php endif; ?>
                        <?php if ($origineProduit == "Bretagne"): ?>
                        <span class="badge-bretagne"><img src="/img/svg/bretagne.png" alt="Bretagne"></span>
                        <?php endif; ?>
                        <!--affiche le message de rupture de stock si nécessaire-->
                        <?php if ($estEnRupture): ?>
                        <div class="rupture-stock">Rupture de stock</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <!--nom du produit-->
                        <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>
                        <div>
                            <span>
                                <!--affiche les étoiles de notation-->
                                <?php for ($i = 1; $i <= 5; $i++):
                                            //détermine le type d'étoile à afficher
                                            if ($noteArrondie >= $i)
                                                $s = 'full';
                                            elseif ($noteArrondie >= $i - 0.5)
                                                $s = 'alf';
                                            else
                                                $s = 'empty';
                                            ?>
                                <img src="/img/svg/star-yellow-<?= $s ?>.svg" alt="Etoile" width="20">
                                <?php endfor; ?>
                            </span>
                            <!--affiche le nombre d'avis-->
                            <span>(<?= $produitCourant['nombre_avis'] ?>)</span>
                        </div>
                        <div>
                            <span>
                                <!--affiche le prix barré s'il y a une réduction-->
                                <?php if ($possedePourcentageRemise): ?>
                                <span
                                    style="text-decoration: line-through; color: #999; margin-right: 5px; font-size: 1.2em;">
                                    <?= number_format($prixOriginalTTC, 2, ',', ' ') ?>€
                                </span>
                                <?php endif; ?>
                            </span>
                            <!--affiche le prix final-->
                            <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                        </div>
                        <p style="padding-bottom: 10px;">Vendeur : <?= htmlspecialchars($nomVendeur) ?></p>
                        <!--bouton pour ajouter au panier-->
                        <button <?= $estEnRupture ? 'disabled' : '' ?>
                            onclick="event.stopPropagation(); ajouterAuPanier(<?= $produitCourant['id_produit'] ?>)">
                            <?= $estEnRupture ? 'Indisponible' : '<img src="/img/svg/panier.svg" alt="Panier" class="panier-icon"> Ajouter au panier' ?>
                        </button>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
    //inclut le pied de page
    include __DIR__ . '/partials/footer.html';
    //inclut le système de notifications toast
    include __DIR__ . '/partials/toast.html';
    //inclut les modales
    include __DIR__ . '/partials/modal.html';
    ?>

    <!--charge le script pour les notifications-->
    <script src="/js/notifications.js"></script>
    <!--charge le script principal de la page-->
    <script src="/js/Index/script.js"></script>
</body>

</html>