<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

session_start();

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/selectBDD.php';
require_once __DIR__ . '/pages/fonctions.php';

// Configuration de la base de données
$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// GESTION DE LA SESSION ET DU PANIER
// ============================================

$idClient = $_SESSION['idClient'] ?? null;
$idPanier = null;

if ($idClient === null) {
    // Utilisateur non connecté : panier temporaire en session
    if (!isset($_SESSION['panierTemp'])) {
        $_SESSION['panierTemp'] = [];
    }
} else {
    // Utilisateur connecté : récupération ou création du panier
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL AND id_client = :idClient
    ";
    $stmtPanier = $connexionBaseDeDonnees->prepare($sqlPanierClient);
    $stmtPanier->execute([':idClient' => $idClient]);
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $idPanier = (int) $panier['id_panier'];
    } else {
        // Création d'un nouveau panier
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";
        $stmtCreate = $connexionBaseDeDonnees->prepare($sqlCreatePanier);
        $stmtCreate->execute([':idClient' => $idClient]);
        $idPanier = (int) $stmtCreate->fetchColumn();
    }

    $_SESSION['panierEnCours'] = $idPanier;
    transfererPanierTempVersBDD($connexionBaseDeDonnees, $idPanier);
}

// ============================================
// TRAITEMENT AJAX : AJOUT AU PANIER
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    header('Content-Type: application/json');

    $idProduit = $_POST['idProduit'] ?? null;
    $quantite = (int) ($_POST['quantite'] ?? 1);

    if (!$idProduit) {
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
        exit;
    }

    try {
        if ($idClient === null) {
            $resultat = ajouterArticleSession($connexionBaseDeDonnees, $idProduit, $quantite);
        } else {
            if (!$idPanier) {
                echo json_encode(['success' => false, 'message' => 'Aucun panier en cours']);
                exit;
            }
            $resultat = ajouterArticleBDD($connexionBaseDeDonnees, $idProduit, $idPanier, $quantite);
        }
        echo json_encode($resultat);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// CHARGEMENT DES DONNÉES PRODUITS
// ============================================

$donnees = chargerProduitsBDD($connexionBaseDeDonnees);
$listeProduits = $donnees['produits'];
$listeCategories = $donnees['categories'];
$totalProduitsSansFiltre = count($listeProduits);

// ============================================
// GESTION DES RECHERCHES
// ============================================

$rechercheVendeur = trim($_POST['vendeur'] ?? '');
$rechercheNom = trim($_POST['nomChercher'] ?? '');

// Recherche par vendeur
if (!empty($rechercheVendeur)) {
    $listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $rechercheVendeur);
    $totalProduitsSansFiltre = count($listeProduits);
}

// Recherche par nom de produit
if (!empty($rechercheNom)) {
    try {
        $listeProduits = chercherProduitsNom($connexionBaseDeDonnees, $rechercheNom);
        $totalProduitsSansFiltre = count($listeProduits);
    } catch (PDOException $e) {
        $messageErreur = htmlspecialchars($e->getMessage());
    }
}

// ============================================
// CALCUL DU PRIX MAXIMUM
// ============================================

$prixMaximum = 0;
if (!empty($listeProduits)) {
    $prixMaximumHT = max(array_column($listeProduits, 'p_prix'));
    
    foreach ($listeProduits as $produitTmp) {
        if ((float) $produitTmp['p_prix'] === (float) $prixMaximumHT) {
            $prixMaximum = round(calcPrixTVA(
                $produitTmp['id_produit'],
                $produitTmp['tva'],
                $prixMaximumHT
            ));
            break;
        }
    }
}

// ============================================
// RÉCUPÉRATION ET APPLICATION DES FILTRES
// ============================================

$categorieSelection = $_POST['categorie'] ?? 'all';
$triSelection = $_POST['tri'] ?? 'meilleures_ventes';
$prixMaximumFiltre = isset($_POST['price']) ? (float) $_POST['price'] : $prixMaximum;
$noteMinimumFiltre = isset($_POST['note_min']) ? (int) $_POST['note_min'] : 0;
$enStockSeulement = isset($_POST['stock_only']);

$filtres = [
    'categorieFiltre' => $categorieSelection,
    'noteMinimum' => $noteMinimumFiltre,
    'prixMaximum' => $prixMaximumFiltre,
    'enStockSeulement' => $enStockSeulement
];

// Application des filtres (sauf si recherche par nom)
if (empty($rechercheNom)) {
    $listeProduits = filtrerProduits($listeProduits, $filtres);
}

// Application du tri
$listeProduits = trierProduits($listeProduits, $triSelection);

// Préparation des catégories pour l'affichage
$tousLesProduits = count($listeProduits);
$categoriesAffichage = preparercategories_affichage($listeCategories);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="icon" type="image/png" href="/img/favicon.svg">
    <link rel="stylesheet" href="/styles/Index/style.css">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
</head>

<body>
    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="container">
        <aside>
            <form method="POST" action="" id="filterForm">
                <div>
                    <span>Tri par :</span>
                    <select name="tri" id="triSelect">
                        <option value="meilleures_ventes"
                            <?= $triSelection === 'meilleures_ventes' ? 'selected' : '' ?>>Meilleures ventes</option>
                        <option value="prix_croissant" <?= $triSelection === 'prix_croissant' ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <option value="prix_decroissant" <?= $triSelection === 'prix_decroissant' ? 'selected' : '' ?>>
                            Prix décroissant</option>
                        <option value="note" <?= $triSelection === 'note' ? 'selected' : '' ?>>Mieux notés</option>
                        <option value="en_promotion" <?= $triSelection === 'en_promotion' ? 'selected' : '' ?>>
                            En promotion</option>
                        <option value="en_reduction" <?= $triSelection === 'en_reduction' ? 'selected' : '' ?>>
                            En réduction</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" id="clearFiltersBtn">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <select name="categorie" id="categorieSelect">
                        <option value="all" <?= $categorieSelection === 'all' ? 'selected' : '' ?>>Tous les produits
                            (<?= $totalProduitsSansFiltre ?>)</option>
                        <?php foreach ($categories_affichage as $categorieCourante): ?>
                        <?php if ($categorieCourante['category'] !== 'all'): ?>
                        <option value="<?= htmlspecialchars($categorieCourante['category']) ?>"
                            <?= $categorieSelection === $categorieCourante['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categorieCourante['category']) ?> (<?= $categorieCourante['count'] ?>)
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </section>

                <div class="search-container" style="justify-content: normal;">
                    <img src="/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                    <input type="text" id="searchVendeur" name="vendeur" placeholder="Rechercher vendeur..."
                        class="search-input" value="<?= htmlspecialchars($_POST['vendeur'] ?? '') ?>">
                </div>


                <section class="no-hover">
                    <h4 style="padding-top: 1em;">Prix</h4>
                    <div>
                        <input type="range" id="priceRange" min="0" max="<?= $prixMaximum ?>"
                            value="<?= isset($_POST['price']) ? $_POST['price'] : $prixMaximum ?>">
                    </div>
                    <div>
                        <span>0€</span>
                        <input type="number" name="price" id="priceValue" class="price-input" step="1" min="0"
                            max="<?= $prixMaximum ?>"
                            value="<?= isset($_POST['price']) ? $_POST['price'] : $prixMaximum ?>">

                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <div class="star-rating-filter" id="starFilterWidget">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <button type="button" class="star-btn" data-value="<?= $i ?>" aria-label="Note <?= $i ?>">
                            <img src="/img/svg/star-empty.svg" alt="" width="24" height="24">
                        </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="note_min" id="inputNoteMin" value="<?= $noteMinimumFiltre ?>">
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="stock_only" id="stockOnlyCheckbox"
                            <?= isset($_POST['stock_only']) ? 'checked' : '' ?>>
                        <span>En stock uniquement</span>
                    </label>
                </section>
            </form>
        </aside>

        <main>
            <div>
                <?php if (empty($listeProduits)): ?>
                <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                <?php foreach ($listeProduits as $produitCourant):
                        $estEnRupture = $produitCourant['p_stock'] <= 0;
                        $discount = (float)$produitCourant['reduction_pourcentage'];
                        $possedePourcentageRemise = $discount > 0;
                        $prixDiscount = ($discount > 0) ? $produitCourant['p_prix'] * (1 - $discount/100) : $produitCourant['p_prix'];
                        $prixFinal = calcPrixTVA($produitCourant['id_produit'], $produitCourant['tva'], $prixDiscount);
                        $prixOriginalTTC = calcPrixTVA($produitCourant['id_produit'], $produitCourant['tva'], $produitCourant['p_prix']);
                        $noteArrondie = round($produitCourant['note_moyenne'] ?? 0);
                        $estEnPromotion = !empty($produitCourant['estenpromo']); 
                        ?>
                <article
                    class="<?= $estEnRupture ? 'produit-rupture' : '' ?> <?= $estEnPromotion ? 'produit-promotion' : '' ?>"
                    onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">
                    <div>
                        <div>
                            <img src="<?= str_replace("html/img/photo", "/img/photo", htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg')) ?>"
                                alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>"
                                class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                        </div>
                        <?php if ($possedePourcentageRemise): ?>
                        <span class="badge-reduction">-<?= round($produitCourant['reduction_pourcentage']) ?>%</span>
                        <?php endif; ?>
                        <?php if ($estEnRupture): ?>
                        <div class="rupture-stock">Rupture de stock</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>
                        <div>
                            <span>
                                <?php for ($i = 1; $i <= 5; $i++):
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
                            <span>(<?= $produitCourant['nombre_avis'] ?>)</span>
                        </div>
                        <div>
                            <span>
                                <?php if ($possedePourcentageRemise): ?>
                                <span style="text-decoration: line-through; color: #999; margin-right: 5px; font-size:
                                    1.2em;"><?= number_format($prixOriginalTTC, 2, ',', ' ') ?>€</span>
                                <?php endif; ?>
                            </span>
                            <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                        </div>
                        <button <?= $estEnRupture ? 'disabled' : '' ?>
                            onclick=" event.stopPropagation(); ajouterAuPanier(<?= $produitCourant['id_produit'] ?>)">
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
    include __DIR__ . '/partials/footer.html';
    include __DIR__ . '/partials/toast.html';
    include __DIR__ . '/partials/modal.html';
    ?>

    <script src="/js/notifications.js"></script>
    <script src="/js/Index/script.js"></script>
</body>

</html>