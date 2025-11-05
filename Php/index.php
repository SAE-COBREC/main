<?php
// Lecture du fichier CSV
$csvFile = __DIR__ . '/BDD/data.csv';
$products = [];
$categories = [];


if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    if ($handle !== FALSE) {
        // Lecture de l'en-tête
        $header = fgetcsv($handle, 1000, ',');

        // Lecture des données
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) === count($header)) {
                $product = array_combine($header, $data);

                // Conversion des types
                $product['id_produit'] = (int) $product['id_produit'];
                $product['p_prix'] = (float) $product['p_prix'];
                $product['p_stock'] = (int) $product['p_stock'];
                $product['p_note'] = (float) $product['p_note'];
                $product['p_nb_ventes'] = (int) $product['p_nb_ventes'];
                $product['discount_percentage'] = (float) $product['discount_percentage'];
                $product['review_count'] = (int) $product['review_count'];
                $product['avg_rating'] = (float) $product['avg_rating'];

                $products[] = $product;

                // Construction des catégories avec comptage
                $category = $product['category'];
                if (!isset($categories[$category])) {
                    $categories[$category] = 0;
                }
                $categories[$category]++;
            }
        }
        fclose($handle);
    }
} else {
    // Utiliser les données par défaut si le CSV n'existe pas
    $products = $defaultProducts;
    foreach ($products as $product) {
        $category = $product['category'];
        if (!isset($categories[$category])) {
            $categories[$category] = 0;
        }
        $categories[$category]++;
    }
}

// Récupération des filtres
$category = $_GET['category'] ?? 'all';
$minRating = $_GET['rating'] ?? 0;
$maxPrice = $_GET['price'] ?? 3000;
$inStockOnly = isset($_GET['in_stock']);
$sortBy = $_GET['sort'] ?? 'best_sellers';

// Filtrage des produits
$filteredProducts = [];

foreach ($products as $product) {
    // Filtre par prix
    if ($product['p_prix'] > $maxPrice) {
        continue;
    }

    // Filtre par catégorie
    if ($category !== 'all' && $product['category'] !== $category) {
        continue;
    }

    // Filtre par stock
    if ($inStockOnly && $product['p_stock'] <= 0) {
        continue;
    }

    // Filtre par note
    if ($product['avg_rating'] < $minRating) {
        continue;
    }

    // Vérification du statut
    if ($product['p_statut'] !== 'En ligne') {
        continue;
    }

    $filteredProducts[] = $product;
}

// Tri des produits
switch ($sortBy) {
    case 'best_sellers':
        usort($filteredProducts, function ($a, $b) {
            return $b['p_nb_ventes'] - $a['p_nb_ventes'];
        });
        break;
    case 'price_asc':
        usort($filteredProducts, function ($a, $b) {
            return $a['p_prix'] - $b['p_prix'];
        });
        break;
    case 'price_desc':
        usort($filteredProducts, function ($a, $b) {
            return $b['p_prix'] - $a['p_prix'];
        });
        break;
    case 'rating':
        usort($filteredProducts, function ($a, $b) {
            return $b['avg_rating'] - $a['avg_rating'];
        });
        break;
}

$products = $filteredProducts;

// Préparation des catégories pour l'affichage
$categoriesDisplay = [];
$totalProducts = 0;

foreach ($categories as $categoryName => $count) {
    $categoriesDisplay[] = [
        'category' => $categoryName,
        'count' => $count
    ];
    $totalProducts += $count;
}

// Ajout de l'option "Tous les produits"
array_unshift($categoriesDisplay, [
    'category' => 'all',
    'count' => $totalProducts
]);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="stylesheet" href="./Index/style.css">
    <link rel="stylesheet" href="../src/styles/stylesHeader.css">
</head>

<body>
    <header class="site-header" role="banner">
        <div class="header-inner">
            <div class="logo-container">
                <a href="/" class="brand">
                    <img src="/src/img/svg/logo-text.svg" alt="Alizon" class="logo" />
                </a>
            </div>

            <div class="search-container">
                <img src="/src/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                <input type="text" placeholder="Rechercher des produits..." class="search-input">
            </div>

            <div class="icons-container">
                <a href="#" class="icon-link">
                    <img src="/src/img/svg/profile.svg" alt="Profile" class="fas fa-shopping-cart icon">
                </a>
                <a href="#" class="icon-link">
                    <img src="/src/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                        style="filter: invert(1) saturate(0.9);">
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <aside>
            <form method="GET" action="" id="filterForm">
                <div>
                    <span>Tri par :</span>
                    <select name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="best_sellers" <?= $sortBy === 'best_sellers' ? 'selected' : '' ?>>Meilleures ventes
                        </option>
                        <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Prix décroissant
                        </option>
                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="window.location.href='index.php'">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <div onclick="setCategory('all')">
                        <span>Tous les produits</span>
                        <span><?= $totalProducts ?></span>
                    </div>
                    <?php foreach ($categoriesDisplay as $cat): ?>
                        <?php if ($cat['category'] !== 'all'): ?>
                            <div onclick="setCategory('<?= htmlspecialchars($cat['category']) ?>')">
                                <span><?= htmlspecialchars($cat['category']) ?></span>
                                <span><?= $cat['count'] ?></span>
                            </div>
                        <?php endif; ?>
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
                        <div onclick="setRating(<?= $i ?>)">
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
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $isOutOfStock = $product['p_stock'] <= 0;
                        $hasDiscount = !empty($product['discount_percentage']) && $product['discount_percentage'] > 0;
                        $finalPrice = $hasDiscount
                            ? $product['p_prix'] * (1 - $product['discount_percentage'] / 100)
                            : $product['p_prix'];
                        $rating = $product['avg_rating'] ? round($product['avg_rating']) : 0;
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
            alert('Produit ' + productId + ' ajouté au panier !');
        }
    </script>
</body>

</html>