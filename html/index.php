<?php
//démarrer la session pour récupérer les informations du client
session_start();

//inclure le fichier de configuration pour la connexion à la base de données
include __DIR__ . '/selectBDD.php';

//inclure les fonctions utilitaires
include __DIR__ . '/pages/fonctions.php';

//récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

//définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

//récupérer l'ID client si connecté
$idClient = $_SESSION['idClient'] ?? null;

if ($idClient === null) {
    //si l'utilisateur n'est pas connecté, on utilise un panier temporaire en SESSION
    if (!isset($_SESSION['panierTemp'])) {
        $_SESSION['panierTemp'] = array();
    }
    $panier = null; //pas de panier en BDD
} else {
    //sinon on récupère l'id de son panier courant (celui qui est en train d'être rempli)
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL
        AND id_client = :idClient
    ";
    $stmtPanier = $connexionBaseDeDonnees->prepare($sqlPanierClient);
    $stmtPanier->execute([":idClient" => $idClient]);
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $idPanier = (int) $panier['id_panier'];
    } else {
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";
        $stmtCreate = $connexionBaseDeDonnees->prepare($sqlCreatePanier);
        $stmtCreate->execute([":idClient" => $idClient]);
        $idPanier = (int) $stmtCreate->fetchColumn();
    }

    $_SESSION["panierEnCours"] = $idPanier;

    transfererPanierTempVersBDD($connexionBaseDeDonnees, $idPanier);
}

//gérer l'ajout au panier via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    header('Content-Type: application/json');

    $idProduit = $_POST['idProduit'] ?? null;
    $quantite = $_POST['quantite'] ?? 1;
    $idClient = $_SESSION['idClient'] ?? null;

    if (!$idProduit) {
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
        exit;
    }

    try {
        if ($idClient === null) {
            //utilisateur non connecté : utiliser le panier temporaire en SESSION
            $resultat = ajouterArticleSession($connexionBaseDeDonnees, $idProduit, $quantite);
        } else {
            //utilisateur connecté : utiliser le panier en BDD
            $idPanier = $_SESSION['panierEnCours'] ?? null;
            if (!$idPanier) {
                echo json_encode(['success' => false, 'message' => 'Aucun panier en cours pour ce client']);
                exit;
            }
            $resultat = ajouterArticleBDD($connexionBaseDeDonnees, $idProduit, $idPanier, $quantite);
        }
    } catch (Exception $e) {
        $resultat = ['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()];
    }

    echo json_encode($resultat);
    exit;
}

$donnees = chargerProduitsBDD($connexionBaseDeDonnees);
$listeProduits = $donnees['produits'];
$listeCategories = $donnees['categories'];

$totalProduitsSansFiltre = count($donnees['produits']);

// Calculer le prix maximum global (TTC) à partir de tous les produits (avant filtrage)
$prixMaximum = 0;
if (!empty($donnees['produits'])) {
    // prix HT maximum
    $prixMaximumHT = max(array_column($donnees['produits'], 'p_prix'));
    // trouver un produit qui a ce prix HT pour récupérer sa TVA
    foreach ($donnees['produits'] as $produitTmp) {
        if ((float)$produitTmp['p_prix'] === (float)$prixMaximumHT) {
            $prixMaximum = round(calcPrixTVA($produitTmp['id_produit'], $produitTmp['tva'], $prixMaximumHT));
            break;
        }
    }
}

//récup la catégorie sélectionnée (par défaut: toutes les catégories)
$categorieSelection = $_POST['categorie'] ?? 'all';

//récup l'option de tri sélectionnée (par défaut: meilleures ventes)
$triSelection = $_POST['tri'] ?? 'meilleures_ventes';

//récup le prix maximum sélectionné depuis le formulaire (valeur du slider)
$prixMaximumFiltre = isset($_POST['price']) ? (float)$_POST['price'] : $prixMaximum;

//récup la note minimum sélectionnée (par défaut: 0)
$noteMinimumFiltre = isset($_POST['note_min']) ? (int)$_POST['note_min'] : 0;

//récup le filtre en stock uniquement (par défaut: false)
$enStockSeulement = isset($_POST['stock_only']) ? true : false;

//tableau contenant tous les filtres
$filtres = [
    'categorieFiltre' => $categorieSelection,
    'noteMinimum' => $noteMinimumFiltre,
    'prixMaximum' => $prixMaximumFiltre,
    'enStockSeulement' => $enStockSeulement
];

// Récupération de la recherche vendeur
$rechercheVendeur = $_POST['vendeur'] ?? '';

// Charger les produits depuis la BDD
$donnees = chargerProduitsBDD($connexionBaseDeDonnees);
$listeProduits = $donnees['produits'];
$listeCategories = $donnees['categories'];

// Si une recherche vendeur est effectuée, filtrer les résultats
if (!empty(trim($rechercheVendeur))) {
    $listeProduits = ProduitDenominationVendeur1($connexionBaseDeDonnees, trim($rechercheVendeur));
}

$totalProduitsSansFiltre = count($listeProduits);


//appliquer les filtres et le tri sélectionnés via les fonctions
$listeProduits = filtrerProduits($listeProduits, $filtres);
$listeProduits = trierProduits($listeProduits, $triSelection);

$tousLesProduits = count($listeProduits);
$categories_affichage = preparercategories_affichage($listeCategories);
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
    <style>
    .star-rating-filter {
        display: flex;
        gap: 5px;
        margin-top: 10px;
    }

    .star-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        transition: transform 0.1s;
    }

    .star-btn:hover {
        transform: scale(1.1);
    }

    .price-input {
        max-width: 150px;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        font-family: 'Poppins', sans-serif;
        background-color: #fff;
        color: #333;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .price-input:focus {
        outline: none;
        border-color: #000;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
    }

    .price-input:hover {
        border-color: #999;
    }

    /* Style des flèches du number input */
    .price-input::-webkit-inner-spin-button,
    .price-input::-webkit-outer-spin-button {
        opacity: 1;
        height: 40px;
    }
    </style>
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
    <script>
    // Gestion du changement de tri
    document.addEventListener('DOMContentLoaded', () => {
        const triSelect = document.getElementById('triSelect');
        if (triSelect) {
            triSelect.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }

        // Gestion du changement de catégorie
        const categorieSelect = document.getElementById('categorieSelect');
        if (categorieSelect) {
            categorieSelect.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });

    // Gestion du slider de prix et du champ numérique (le champ `name="price"`)
    document.addEventListener('DOMContentLoaded', () => {
        const priceRange = document.getElementById('priceRange');
        const priceInput = document.getElementById('priceValue'); // now an <input name="price">

        if (priceRange && priceInput) {
            const setAll = (v) => {
                const num = Math.round((parseFloat(v) || 0) * 100) / 100;
                priceRange.value = num;
                priceInput.value = num;
            };

            // initial sync
            setAll(priceInput.value);

            // slider -> input
            priceRange.addEventListener('input', function() {
                setAll(this.value);
            });

            // submit on change (end of interaction)
            priceRange.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });

            // input -> slider, submit on change
            priceInput.addEventListener('input', function() {
                // keep inside bounds
                const max = parseFloat(this.max) || parseFloat(priceRange.max) || 0;
                let v = parseFloat(this.value) || 0;
                if (v < 0) v = 0;
                if (v > max) v = max;
                setAll(v);
            });
            priceInput.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });

    // Gestion du checkbox "En stock uniquement"
    document.addEventListener('DOMContentLoaded', () => {
        const stockOnlyCheckbox = document.getElementById('stockOnlyCheckbox');
        if (stockOnlyCheckbox) {
            stockOnlyCheckbox.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });

    // Gestion du bouton Effacer les filtres
    document.addEventListener('DOMContentLoaded', () => {
        const clearBtn = document.getElementById('clearFiltersBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // Réinitialiser tous les filtres
                document.getElementById('categorieSelect').value = 'all';
                const maxVal = document.getElementById('priceRange').max;
                document.getElementById('priceRange').value = maxVal;
                const priceEl = document.getElementById('priceValue');
                if (priceEl) {
                    priceEl.value = maxVal;
                }
                document.getElementById('searchVendeur').value = '';
                document.getElementById('inputNoteMin').value = '0';
                document.getElementById('triSelect').value = 'meilleures_ventes';
                document.getElementById('stockOnlyCheckbox').checked = false;
                // Réinitialiser les étoiles
                const btns = document.querySelectorAll('.star-btn');
                btns.forEach(b => {
                    b.querySelector('img').src = '/img/svg/star-empty.svg';
                });
                // Soumettre le formulaire
                document.getElementById('filterForm').submit();
            });
        }
    });

    // Gestion du sélecteur d'étoiles (Filtres)
    document.addEventListener('DOMContentLoaded', () => {
        const widget = document.getElementById('starFilterWidget');
        const input = document.getElementById('inputNoteMin');
        let selectedValue = input ? parseInt(input.value) : 0;

        if (widget) {
            const btns = widget.querySelectorAll('.star-btn');

            const updateStars = (val) => {
                btns.forEach(b => {
                    const v = parseInt(b.dataset.value);
                    const img = b.querySelector('img');
                    // Change l'image selon la valeur (full ou empty)
                    img.src = v <= val ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
                });
            };

            // Initialiser l'affichage avec la valeur sauvegardée
            updateStars(selectedValue);

            btns.forEach(btn => {
                // Survol : affiche les étoiles jusqu'au curseur
                btn.addEventListener('mouseenter', () => updateStars(btn.dataset.value));

                // Clic : sélectionne la note
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectedValue = parseInt(btn.dataset.value);
                    if (input) input.value = selectedValue;
                    updateStars(selectedValue);

                    // Soumettre le formulaire pour appliquer le filtre
                    document.getElementById('filterForm').submit();
                });
            });

            // Sortie de souris : revient à la valeur sélectionnée
            widget.addEventListener('mouseleave', () => updateStars(selectedValue));
        }
    });

    //fonction pour ajouter au panier avec requête AJAX vers la base de données
    function ajouterAuPanier(idProduit) {
        const formData = new FormData();
        formData.append('action', 'ajouter_panier');
        formData.append('idProduit', idProduit);
        formData.append('quantite', 1);

        fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const message = data.success ? data.message : data.message;
                const type = data.success ? 'success' : 'error';
                window.notify ? notify(message, type) : alert((data.success ? '✓ ' : '✗ ') + message);
            })
            .catch(error => {
                console.error('Erreur:', error);
                window.notify ? notify('Erreur lors de l\'ajout au panier', 'error') : alert(
                    'Erreur lors de l\'ajout au panier');
            });
    }

    // Gestion de la recherche vendeur
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchVendeur');

        if (searchInput) {
            // Recherche en temps réel (500ms après la dernière frappe)
            let timeoutId;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 500);
            });

            // Recherche sur Enter
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('filterForm').submit();
                }
            });
        }
    });
    </script>
</body>

</html>