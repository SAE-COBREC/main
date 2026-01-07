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
$tousLesProduits = count($listeProduits);
$categories_affichage = preparercategories_affichage($listeCategories);
$prixMaximum = max(array_column($listeProduits, 'p_prix'));
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
                    <select>
                        <option value="meilleures_ventes" <?= $tri_par === 'meilleures_ventes' ? 'selected' : '' ?>>
                            Meilleures ventes
                        </option>
                        <option value="prix_croissant" <?= $tri_par === 'prix_croissant' ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <option value="prix_decroissant" <?= $tri_par === 'prix_decroissant' ? 'selected' : '' ?>>Prix
                            décroissant
                        </option>
                        <option value="note" <?= $tri_par === 'note' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <select>
                        <option value="all">Tous les produits (<?= $tousLesProduits ?>)</option>
                        <?php foreach ($categories_affichage as $categorieCourante): ?>
                        <?php if ($categorieCourante['category'] !== 'all'): ?>
                        <option value="<?= htmlspecialchars($categorieCourante['category']) ?>">
                            <?= htmlspecialchars($categorieCourante['category']) ?> (<?= $categorieCourante['count'] ?>)
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </section>


                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="<?= $prixMaximum ?>" value="<?= $prixMaximum ?>">
                    </div>
                    <div>
                        <span>0€</span>
                        <span><?= $prixMaximum ?>€</span>
                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div onclick="definirNote(<?= $i ?>)">
                        <span><?= str_repeat('<img src="/img/svg/star-full.svg" alt="★" width="16" style="margin-right:3px;">', $i) . str_repeat('<img src="/img/svg/star-empty.svg" alt="☆" width="16">', 5 - $i) ?></span>
                        <span><?= $i ?> et plus</span>
                    </div>
                    <?php endfor; ?>
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" <?= $enStockSeulement ? 'checked' : '' ?>>
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
                        $discount = (float)$produitCourant['pourcentage_reduction'];
                        $possedePourcentageRemise = $discount > 0;
                        $prixDiscount = ($discount > 0) ? $produitCourant['p_prix'] * (1 - $discount/100) : $produitCourant['p_prix'];
                        $prixFinal = calcPrixTVA($produitCourant['id_produit'], $produitCourant['tva'], $prixDiscount);
                        $prixOriginalTTC = calcPrixTVA($produitCourant['id_produit'], $produitCourant['tva'], $produitCourant['p_prix']);
                        $noteArrondie = round($produitCourant['note_moyenne'] ?? 0);
                        ?>
                <article class="<?= $estEnRupture ? 'produit-rupture' : '' ?>"
                    onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">
                    <div>
                        <div>
                            <img src="<?= str_replace("html/img/photo", "/img/photo", htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg')) ?>"
                                alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>"
                                class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                        </div>
                        <?php if ($possedePourcentageRemise): ?>
                        <span class="badge-reduction">-<?= round($produitCourant['pourcentage_reduction']) ?>%</span>
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
    </script>
</body>

</html>