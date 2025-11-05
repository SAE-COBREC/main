<?php
// Connexion à la base de données
require_once 'config/database.php';

// Récupération des filtres
$category = $_GET['category'] ?? 'all';
$minRating = $_GET['rating'] ?? 0;
$maxPrice = $_GET['price'] ?? 3000;
$inStockOnly = isset($_GET['in_stock']);
$sortBy = $_GET['sort'] ?? 'best_sellers';

// Construction de la requête SQL avec votre schéma
$sql = "SELECT p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
        p.p_note, p.p_nb_ventes,
        COALESCE(r.reduction_pourcentage, 0) as discount_percentage,
        COALESCE(img.i_lien, '../src/img/Photo/galette.webp') as image_url,
        COALESCE(COUNT(DISTINCT c.id_commentaire), 0) as review_count,
        COALESCE(AVG(c.a_note), 0) as avg_rating,
        cat.nom_categorie as category
        FROM cobrec1._produit p
        LEFT JOIN cobrec1._en_reduction er ON p.id_produit = er.id_produit
        LEFT JOIN cobrec1._reduction r ON er.id_reduction = r.id_reduction 
            AND r.reduction_debut <= CURRENT_TIMESTAMP 
            AND r.reduction_fin >= CURRENT_TIMESTAMP
        LEFT JOIN cobrec1._represente_produit rp ON p.id_produit = rp.id_produit
        LEFT JOIN cobrec1._image img ON rp.id_image = img.id_image
        LEFT JOIN cobrec1._commentaire c ON p.id_produit = (
            SELECT a.id_produit FROM cobrec1._avis a WHERE a.id_avis = c.id_avis
        )
        LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cat ON fpd.id_categorie = cat.id_categorie
        WHERE p.p_statut = 'En ligne'
        AND p.p_prix <= :maxPrice";

$params = [':maxPrice' => $maxPrice];

if ($category !== 'all') {
    $sql .= " AND cat.nom_categorie = :category";
    $params[':category'] = $category;
}

if ($inStockOnly) {
    $sql .= " AND p.p_stock > 0";
}

$sql .= " GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
          p.p_note, p.p_nb_ventes, r.reduction_pourcentage, img.i_lien, cat.nom_categorie";

$sql .= " HAVING COALESCE(AVG(c.a_note), 0) >= :minRating";
$params[':minRating'] = $minRating;

// Tri
switch ($sortBy) {
    case 'best_sellers':
        $sql .= " ORDER BY p.p_nb_ventes DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY p.p_prix ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.p_prix DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories avec comptage
$categoriesStmt = $pdo->query("
    SELECT cat.nom_categorie as category, COUNT(DISTINCT p.id_produit) as count 
    FROM cobrec1._categorie_produit cat
    LEFT JOIN cobrec1._fait_partie_de fpd ON cat.id_categorie = fpd.id_categorie
    LEFT JOIN cobrec1._produit p ON fpd.id_produit = p.id_produit AND p.p_statut = 'En ligne'
    GROUP BY cat.nom_categorie
    HAVING COUNT(DISTINCT p.id_produit) > 0
");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
$totalProducts = array_sum(array_column($categories, 'count'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="stylesheet" href="../src/styles/Index/style.css">
    <style>
        /* Fix des chemins pour les images de fond si nécessaire */
    </style>
</head>
<body>
    <header>
        <div><img src="../src/img/svg/logo-text.svg" alt="Logo Alizon"></div>

        <div>
            <form action="search.php" method="GET">
                <input type="text" name="q" placeholder="Rechercher des produits...">
            </form>
        </div>
        <div>
            <button onclick="window.location.href='cart.php'">
                <img src="../src/img/svg/panier.svg" alt="Panier" style="filter: invert(1);">
            </button>
            <button onclick="window.location.href='profile.php'">
                <img src="../src/img/svg/profile.svg" alt="Profil">
            </button>
        </div>
    </header>

    <div class="container">
        <aside>
            <form method="GET" action="" id="filterForm">
                <div>
                    <span>Tri par :</span>
                    <select name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="best_sellers" <?= $sortBy === 'best_sellers' ? 'selected' : '' ?>>Meilleures ventes</option>
                        <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="window.location.href='index.php'">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <div onclick="setCategory('all')" style="cursor: pointer;">
                        <span>Tous les produits</span>
                        <span><?= $totalProducts ?></span>
                    </div>
                    <?php foreach ($categories as $cat): ?>
                    <div onclick="setCategory('<?= htmlspecialchars($cat['category']) ?>')" style="cursor: pointer;">
                        <span><?= htmlspecialchars($cat['category']) ?></span>
                        <span><?= $cat['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </section>

                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="3000" value="<?= $maxPrice ?>" 
                               oninput="updatePriceDisplay(this.value)"
                               onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div>
                        <span>0€</span>
                        <span id="maxPriceDisplay"><?= $maxPrice ?>€</span>
                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div onclick="setRating(<?= $i ?>)" style="cursor: pointer;">
                        <span><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></span>
                        <span><?= $i ?> et plus</span>
                    </div>
                    <?php endfor; ?>
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="in_stock" <?= $inStockOnly ? 'checked' : '' ?>
                               onchange="document.getElementById('filterForm').submit()">
                        <span>En stock uniquement</span>
                    </label>
                </section>

                <input type="hidden" name="category" id="categoryInput" value="<?= htmlspecialchars($category) ?>">
                <input type="hidden" name="rating" id="ratingInput" value="<?= $minRating ?>">
            </form>
        </aside>

        <main>
            <div>
                <?php if (empty($products)): ?>
                    <p style="grid-column: 1/-1; text-align: center; padding: 2rem;">
                        Aucun produit ne correspond à vos critères de recherche.
                    </p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $isOutOfStock = $product['p_stock'] <= 0;
                        $hasDiscount = !empty($product['discount_percentage']) && $product['discount_percentage'] > 0;
                        $finalPrice = $hasDiscount 
                            ? $product['p_prix'] * (1 - $product['discount_percentage'] / 100)
                            : $product['p_prix'];
                        $rating = $product['p_note'] ? round($product['p_note']) : 0;
                        ?>
                        <article onclick="window.location.href='product.php?id=<?= $product['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($product['p_nom']) ?>">
                                </div>
                                <?php if ($hasDiscount): ?>
                                <span>-<?= round($product['discount_percentage']) ?>%</span>
                                <?php endif; ?>
                                <?php if ($isOutOfStock): ?>
                                <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($product['p_nom']) ?></h3>
                                <div>
                                    <span><?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?></span>
                                    <span>(<?= $product['review_count'] ?>)</span>
                                </div>
                                <div>
                                    <?php if ($hasDiscount): ?>
                                    <span><?= number_format($product['p_prix'], 0, ',', ' ') ?>€</span>
                                    <?php endif; ?>
                                    <span><?= number_format($finalPrice, 0, ',', ' ') ?>€</span>
                                </div>
                                <button <?= $isOutOfStock ? 'disabled' : '' ?> 
                                        onclick="event.stopPropagation(); addToCart(<?= $product['id_produit'] ?>)">
                                    <?= $isOutOfStock ? 'Indisponible' : '🛒 Ajouter au panier' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer>
        <div>
            <div>
                <a href="#">f</a>
                <a href="#">in</a>
                <a href="#">▶</a>
                <a href="#">📷</a>
                <a href="#">♪</a>
                <a href="#">P</a>
            </div>

            <nav>
                <section>
                    <h4>Alizon</h4>
                    <ul>
                        <li><a href="#">À propos</a></li>
                        <li><a href="#">Carrières</a></li>
                        <li><a href="#">Investisseurs</a></li>
                        <li><a href="#">Presse et médias</a></li>
                        <li><a href="#">Partenaires</a></li>
                        <li><a href="#">Affiliés</a></li>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">Statut du service</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Produits</h4>
                    <ul>
                        <li><a href="#">Shop</a></li>
                        <li><a href="#">Shop Pay</a></li>
                        <li><a href="#">Shopify Plus</a></li>
                        <li><a href="#">Shopify pour les entreprises</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Développeurs</h4>
                    <ul>
                        <li><a href="#">Alizon.dev</a></li>
                        <li><a href="#">Documentation API</a></li>
                        <li><a href="#">Dev Degree</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Assistance</h4>
                    <ul>
                        <li><a href="#">Assistance aux marchands</a></li>
                        <li><a href="#">Centre d'aide de Alizon</a></li>
                        <li><a href="#">Faire appel à un partenaire</a></li>
                        <li><a href="#">Alizon Academy</a></li>
                        <li><a href="#">Communauté Alizon</a></li>
                    </ul>
                </section>

                <section>
                    <h4>Solutions</h4>
                    <ul>
                        <li><a href="#">Éditeur de boutique en ligne</a></li>
                        <li><a href="#">Outil de création de site web</a></li>
                    </ul>
                </section>
            </nav>

            <div>
                <span>Conditions d'utilisation</span>
                <span>Copyright CGRRSC All right reserved</span>
                <span>Condition de ventes</span>
            </div>
        </div>
    </footer>

    <script>
        function setCategory(category) {
            document.getElementById('categoryInput').value = category;
            document.getElementById('filterForm').submit();
        }

        function setRating(rating) {
            document.getElementById('ratingInput').value = rating;
            document.getElementById('filterForm').submit();
        }

        function updatePriceDisplay(value) {
            document.getElementById('maxPriceDisplay').textContent = value + '€';
        }

        function addToCart(productId) {
            fetch('cart_add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Produit ajouté au panier !');
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        }
    </script>
</body>
</html>