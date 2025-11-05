<?php
// Lecture du fichier CSV
$fichierCSV = __DIR__ . '/BDD/data.csv';
$produits = [];
$categories = [];


if (file_exists($fichierCSV)) {
    $handle = fopen($fichierCSV, 'r');
    if ($handle !== FALSE) {
        // Lecture de l'en-tête
        $entete = fgetcsv($handle, 1000, ',');

        // Lecture des données
        while (($donnees = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($donnees) === count($entete)) {
                $produit = array_combine($entete, $donnees);

                // Conversion des types
                $produit['id_produit'] = (int) $produit['id_produit'];
                $produit['p_prix'] = (float) $produit['p_prix'];
                $produit['p_stock'] = (int) $produit['p_stock'];
                $produit['p_note'] = (float) $produit['p_note'];
                $produit['p_nb_ventes'] = (int) $produit['p_nb_ventes'];
                $produit['discount_percentage'] = (float) $produit['discount_percentage'];
                $produit['review_count'] = (int) $produit['review_count'];
                $produit['avg_rating'] = (float) $produit['avg_rating'];

                $produits[] = $produit;

                // Construction des catégories avec comptage
                $categorie = $produit['category'];
                if (!isset($categories[$categorie])) {
                    $categories[$categorie] = 0;
                }
                $categories[$categorie]++;
            }
        }
        fclose($handle);
    }
}

// Récupération des filtres
$categorieFiltre = $_GET['category'] ?? 'all';
$noteMinimum = $_GET['rating'] ?? 0;
$prixMaximum = $_GET['price'] ?? 3000;
$enStockSeulement = isset($_GET['in_stock']);
$triPar = $_GET['sort'] ?? 'best_sellers';

// Filtrage des produits
$produitsFiltres = [];

foreach ($produits as $produit) {
    // Filtre par prix
    if ($produit['p_prix'] > $prixMaximum) {
        continue;
    }

    // Filtre par catégorie
    if ($categorieFiltre !== 'all' && $produit['category'] !== $categorieFiltre) {
        continue;
    }

    // Filtre par stock
    if ($enStockSeulement && $produit['p_stock'] <= 0) {
        continue;
    }

    // Filtre par note
    if ($produit['avg_rating'] < $noteMinimum) {
        continue;
    }

    // Vérification du statut
    if ($produit['p_statut'] !== 'En ligne') {
        continue;
    }

    $produitsFiltres[] = $produit;
}

// Tri des produits
switch ($triPar) {
    case 'best_sellers':
        usort($produitsFiltres, function ($a, $b) {
            return $b['p_nb_ventes'] - $a['p_nb_ventes'];
        });
        break;
    case 'price_asc':
        usort($produitsFiltres, function ($a, $b) {
            return $a['p_prix'] - $b['p_prix'];
        });
        break;
    case 'price_desc':
        usort($produitsFiltres, function ($a, $b) {
            return $b['p_prix'] - $a['p_prix'];
        });
        break;
    case 'rating':
        usort($produitsFiltres, function ($a, $b) {
            return $b['avg_rating'] - $a['avg_rating'];
        });
        break;
}

$produits = $produitsFiltres;

// Préparation des catégories pour l'affichage
$categoriesAffichage = [];
$totalProduits = 0;

foreach ($categories as $nomCategorie => $compte) {
    $categoriesAffichage[] = [
        'category' => $nomCategorie,
        'count' => $compte
    ];
    $totalProduits += $compte;
}

// Ajout de l'option "Tous les produits"
array_unshift($categoriesAffichage, [
    'category' => 'all',
    'count' => $totalProduits
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
                        <option value="best_sellers" <?= $triPar === 'best_sellers' ? 'selected' : '' ?>>Meilleures ventes
                        </option>
                        <option value="price_asc" <?= $triPar === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_desc" <?= $triPar === 'price_desc' ? 'selected' : '' ?>>Prix décroissant
                        </option>
                        <option value="rating" <?= $triPar === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="window.location.href='index.php'">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <div onclick="definirCategorie('all')">
                        <span>Tous les produits</span>
                        <span><?= $totalProduits ?></span>
                    </div>
                    <?php foreach ($categoriesAffichage as $categorie): ?>
                        <?php if ($categorie['category'] !== 'all'): ?>
                            <div onclick="definirCategorie('<?= htmlspecialchars($categorie['category']) ?>')">
                                <span><?= htmlspecialchars($categorie['category']) ?></span>
                                <span><?= $categorie['count'] ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="3000" value="<?= $prixMaximum ?>"
                            oninput="mettreAJourAffichagePrix(this.value)"
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div>
                        <span>0€</span>
                        <span id="affichagePrixMax"><?= $prixMaximum ?>€</span>
                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div onclick="definirNote(<?= $i ?>)">
                            <span><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></span>
                            <span><?= $i ?> et plus</span>
                        </div>
                    <?php endfor; ?>
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="in_stock" <?= $enStockSeulement ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <span>En stock uniquement</span>
                    </label>
                </section>

                <input type="hidden" name="category" id="champCategorie" value="<?= htmlspecialchars($categorieFiltre) ?>">
                <input type="hidden" name="rating" id="champNote" value="<?= $noteMinimum ?>">
            </form>
        </aside>

        <main>
            <div>
                <?php if (empty($produits)): ?>
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $estEnRupture = $produit['p_stock'] <= 0;
                        $aUneRemise = !empty($produit['discount_percentage']) && $produit['discount_percentage'] > 0;
                        $prixFinal = $aUneRemise
                            ? $produit['p_prix'] * (1 - $produit['discount_percentage'] / 100)
                            : $produit['p_prix'];
                        $note = $produit['avg_rating'] ? round($produit['avg_rating']) : 0;
                        ?>
                        <article onclick="window.location.href='product.php?id=<?= $produit['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= htmlspecialchars($produit['image_url']) ?>"
                                        alt="<?= htmlspecialchars($produit['p_nom']) ?>">
                                </div>
                                <?php if ($aUneRemise): ?>
                                    <span>-<?= round($produit['discount_percentage']) ?>%</span>
                                <?php endif; ?>
                                <?php if ($estEnRupture): ?>
                                    <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($produit['p_nom']) ?></h3>
                                <div>
                                    <span><?= str_repeat('★', $note) . str_repeat('☆', 5 - $note) ?></span>
                                    <span>(<?= $produit['review_count'] ?>)</span>
                                </div>
                                <div>
                                    <?php if ($aUneRemise): ?>
                                        <span><?= number_format($produit['p_prix'], 0, ',', ' ') ?>€</span>
                                    <?php endif; ?>
                                    <span><?= number_format($prixFinal, 0, ',', ' ') ?>€</span>
                                </div>
                                <button <?= $estEnRupture ? 'disabled' : '' ?>
                                    onclick="event.stopPropagation(); ajouterAuPanier(<?= $produit['id_produit'] ?>)">
                                    <?= $estEnRupture ? 'Indisponible' : '🛒 Ajouter au panier' ?>
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
        function definirCategorie(categorie) {
            document.getElementById('champCategorie').value = categorie;
            document.getElementById('filterForm').submit();
        }

        function definirNote(note) {
            document.getElementById('champNote').value = note;
            document.getElementById('filterForm').submit();
        }

        function mettreAJourAffichagePrix(valeur) {
            document.getElementById('affichagePrixMax').textContent = valeur + '€';
        }

        function ajouterAuPanier(idProduit) {
            alert('Produit ' + idProduit + ' ajouté au panier !');
        }
    </script>
</body>

</html>