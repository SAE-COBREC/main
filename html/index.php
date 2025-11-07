<?php
// Fonctions utilitaires

/**
 * Charge les produits depuis un fichier CSV
 * @param string $fichierCSV Chemin vers le fichier CSV
 * @return array Tableau contenant les produits et les catégories
 */
function chargerProduitsCSV($fichierCSV)
{
    $produits = [];
    $categories = [];

    // Vérifie si le fichier existe
    if (file_exists($fichierCSV)) {
        $fichier = fopen($fichierCSV, 'r');
        if ($fichier !== FALSE) {
            // Lit la première ligne (en-têtes)
            $entete = fgetcsv($fichier, 1000, ',');

            // Lit chaque ligne du fichier
            while (($donnees = fgetcsv($fichier, 1000, ',')) !== FALSE) {
                // Vérifie que le nombre de colonnes correspond aux en-têtes
                if (count($donnees) === count($entete)) {
                    // Combine les en-têtes avec les données
                    $produit = array_combine($entete, $donnees);

                    // Convertit les types de données
                    $produit['id_produit'] = (int) $produit['id_produit'];
                    $produit['p_prix'] = (float) $produit['p_prix'];
                    $produit['p_stock'] = (int) $produit['p_stock'];
                    $produit['p_note'] = (float) $produit['p_note'];
                    $produit['p_nb_ventes'] = (int) $produit['p_nb_ventes'];
                    $produit['pourcentage_reduction'] = (float) $produit['pourcentage_reduction'];
                    $produit['nombre_avis'] = (int) $produit['nombre_avis'];
                    $produit['note_moyenne'] = (float) $produit['note_moyenne'];

                    // Ajoute le produit à la liste
                    $produits[] = $produit;

                    // Compte les produits par catégorie
                    $categorie = $produit['category'];
                    if (!isset($categories[$categorie])) {
                        $categories[$categorie] = 0;
                    }
                    $categories[$categorie]++;
                }
            }
            fclose($fichier);
        }
    }

    return ['produits' => $produits, 'categories' => $categories];
}

/**
 * Filtre les produits selon des critères donnés
 * @param array $produits Liste des produits
 * @param array $filtres Critères de filtrage
 * @return array Produits filtrés
 */
function filtrerProduits($produits, $filtres)
{
    $produits_filtres = [];

    foreach ($produits as $produit) {
        // Filtre par prix maximum
        if ($produit['p_prix'] > $filtres['prixMaximum']) {
            continue;
        }

        // Filtre par catégorie
        if ($filtres['categorieFiltre'] !== 'all' && $produit['category'] !== $filtres['categorieFiltre']) {
            continue;
        }

        // Filtre par disponibilité en stock
        if ($filtres['enStockSeulement'] && $produit['p_stock'] <= 0) {
            continue;
        }

        // Filtre par note minimum
        if ($produit['note_moyenne'] < $filtres['noteMinimum']) {
            continue;
        }

        // Filtre par statut (uniquement "En ligne" ou "En rupture")
        if ($produit['p_statut'] !== 'En ligne' && $produit['p_statut'] !== 'En rupture') {
            continue;
        }

        $produits_filtres[] = $produit;
    }

    return $produits_filtres;
}

/**
 * Trie les produits selon un critère donné
 * @param array $produits Liste des produits
 * @param string $tri_par Critère de tri
 * @return array Produits triés
 */
function trierProduits($produits, $tri_par)
{
    switch ($tri_par) {
        case 'best_sellers':
            // Tri par meilleures ventes (décroissant)
            usort($produits, function ($a, $b) {
                return $b['p_nb_ventes'] - $a['p_nb_ventes'];
            });
            break;
        case 'price_asc':
            // Tri par prix croissant
            usort($produits, function ($a, $b) {
                return $a['p_prix'] - $b['p_prix'];
            });
            break;
        case 'price_desc':
            // Tri par prix décroissant
            usort($produits, function ($a, $b) {
                return $b['p_prix'] - $a['p_prix'];
            });
            break;
        case 'rating':
            // Tri par note moyenne (décroissant)
            usort($produits, function ($a, $b) {
                return $b['note_moyenne'] - $a['note_moyenne'];
            });
            break;
    }

    return $produits;
}

/**
 * Prépare les catégories pour l'affichage avec leurs comptes
 * @param array $categories Liste des catégories
 * @return array Catégories formatées pour l'affichage
 */
function preparercategories_affichage($categories)
{
    $categories_affichage = [];
    $total_produits = 0;

    // Formate chaque catégorie avec son compte
    foreach ($categories as $nomCategorie => $compte) {
        $categories_affichage[] = [
            'category' => $nomCategorie,
            'count' => $compte
        ];
        $total_produits += $compte;
    }

    // Ajoute l'option "Tous les produits" en première position
    array_unshift($categories_affichage, [
        'category' => 'all',
        'count' => $total_produits
    ]);

    return $categories_affichage;
}

// Traitement principal

// Charge les données depuis le fichier CSV
$fichierCSV = realpath(__DIR__ . '/../src/data/mls.csv');
$donnees = chargerProduitsCSV($fichierCSV);
$produits = $donnees['produits'];
$categories = $donnees['categories'];

// Récupère les filtres depuis le formulaire ou utilise les valeurs par défaut
$categorieFiltre = $_POST['category'] ?? 'all';
$noteMinimum = $_POST['rating'] ?? 0;
$prixMaximum = $_POST['price'] ?? 3000;
$enStockSeulement = isset($_POST['in_stock']);
$tri_par = $_POST['sort'] ?? 'best_sellers';

// Prépare le tableau de filtres
$filtres = [
    'categorieFiltre' => $categorieFiltre,
    'noteMinimum' => $noteMinimum,
    'prixMaximum' => $prixMaximum,
    'enStockSeulement' => $enStockSeulement
];

// Applique les filtres et le tri
$produits_filtres = filtrerProduits($produits, $filtres);
$produits = trierProduits($produits_filtres, $tri_par);
$categories_affichage = preparercategories_affichage($categories);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="stylesheet" href="/html/styles/Index/style.css">
    <link rel="stylesheet" href="/html/styles/Header/stylesHeader.css">
    <style>
        /* Style pour les images en rupture de stock */
        .image-rupture {
            filter: grayscale(100%) opacity(0.7);
            transition: filter 0.3s ease;
        }

        /* Style pour les produits en rupture */
        .produit-rupture {
            position: relative;
        }

        /* Badge "Rupture de stock" */
        .rupture-stock {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            z-index: 2;
        }
    </style>
</head>

<body>
    <!-- En-tête du site -->
    <header class="site-header" role="banner">
        <div class="header-inner">
            <!-- Logo -->
            <div class="logo-container">
                <a href="/" class="brand">
                    <img src="/html/img/svg/logo-text.svg" alt="Alizon" class="logo" />
                </a>
            </div>

            <!-- Barre de recherche -->
            <div class="search-container">
                <img src="/html/img/svg/loupe.svg" alt="Loupe de recherche"
                    class="fas fa-shopping-cart icon loupe-icon">
                <input type="text" placeholder="Rechercher des produits..." class="search-input">
            </div>

            <!-- Icônes utilisateur et panier -->
            <div class="icons-container">
                <a href="#" class="icon-link">
                    <img src="/html/img/svg/profile.svg" alt="Profile" class="fas fa-shopping-cart icon">
                </a>
                <a href="/html/pages/panier/index.php" class="icon-link">
                    <img src="/html/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                        style="filter: invert(1) saturate(0.9);">
                </a>
            </div>
        </div>
    </header>

    <!-- Contenu principal -->
    <div class="container">
        <!-- Barre latérale avec filtres -->
        <aside>
            <form method="POST" action="" id="filterForm">
                <!-- Sélecteur de tri -->
                <div>
                    <span>Tri par :</span>
                    <select name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="best_sellers" <?= $tri_par === 'best_sellers' ? 'selected' : '' ?>>Meilleures ventes
                        </option>
                        <option value="price_asc" <?= $tri_par === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_desc" <?= $tri_par === 'price_desc' ? 'selected' : '' ?>>Prix décroissant
                        </option>
                        <option value="rating" <?= $tri_par === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <!-- Titre des filtres avec bouton de réinitialisation -->
                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="reinitialiserFiltres()">Effacer</button>
                </div>

                <!-- Filtre par catégorie -->
                <section>
                    <h4>Catégories</h4>
                    <div onclick="definirCategorie('all')">
                        <span>Tous les produits</span>
                        <span><?= $categories_affichage[0]['count'] ?></span>
                    </div>
                    <?php foreach ($categories_affichage as $categorie): ?>
                        <?php if ($categorie['category'] !== 'all'): ?>
                            <div onclick="definirCategorie('<?= htmlspecialchars($categorie['category']) ?>')">
                                <span><?= htmlspecialchars($categorie['category']) ?></span>
                                <span><?= $categorie['count'] ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <!-- Filtre par prix -->
                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="3000" value="<?= $prixMaximum ?>"
                            oninput="mettreAJourAffichagePrix(this.value)"
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div>
                        <span>0€</span>
                        <span id="affichagePrixMax" ondblclick="activerEditionPrix()"><?= $prixMaximum ?>€</span>
                    </div>
                </section>

                <!-- Filtre par note -->
                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div onclick="definirNote(<?= $i ?>)">
                            <span><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></span>
                            <span><?= $i ?> et plus</span>
                        </div>
                    <?php endfor; ?>
                </section>

                <!-- Filtre par disponibilité -->
                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="in_stock" <?= $enStockSeulement ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <span>En stock uniquement</span>
                    </label>
                </section>

                <!-- Champs cachés pour les filtres -->
                <input type="hidden" name="category" id="champCategorie"
                    value="<?= htmlspecialchars($categorieFiltre) ?>">
                <input type="hidden" name="rating" id="champNote" value="<?= $noteMinimum ?>">
            </form>
        </aside>

        <!-- Zone principale d'affichage des produits -->
        <main>
            <div>
                <?php if (empty($produits)): ?>
                    <!-- Message si aucun produit ne correspond aux filtres -->
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <!-- Boucle d'affichage des produits -->
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        // Détermine l'état du produit
                        $estEnRupture = $produit['p_stock'] <= 0;
                        $aUneRemise = !empty($produit['pourcentage_reduction']) && $produit['pourcentage_reduction'] > 0;
                        $prixFinal = $aUneRemise
                            ? $produit['p_prix'] * (1 - $produit['pourcentage_reduction'] / 100)
                            : $produit['p_prix'];
                        $note = $produit['note_moyenne'] ? round($produit['note_moyenne']) : 0;
                        ?>
                        <!-- Carte produit -->
                        <article class="<?= $estEnRupture ? 'produit-rupture' : '' ?>"
                            onclick="window.location.href='produit.php?id=<?= $produit['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= htmlspecialchars($produit['image_url']) ?>"
                                        alt="<?= htmlspecialchars($produit['p_nom']) ?>"
                                        class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                                </div>
                                <!-- Badge de réduction si applicable -->
                                <?php if ($aUneRemise): ?>
                                    <span>-<?= round($produit['pourcentage_reduction']) ?>%</span>
                                <?php endif; ?>
                                <!-- Badge de rupture de stock si applicable -->
                                <?php if ($estEnRupture): ?>
                                    <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <!-- Nom du produit -->
                                <h3><?= htmlspecialchars($produit['p_nom']) ?></h3>
                                <!-- Note et nombre d'avis -->
                                <div>
                                    <span><?= str_repeat('★', $note) . str_repeat('☆', 5 - $note) ?></span>
                                    <span>(<?= $produit['nombre_avis'] ?>)</span>
                                </div>
                                <!-- Prix -->
                                <div>
                                    <?php if ($aUneRemise): ?>
                                        <span><?= number_format($produit['p_prix'], 0, ',', ' ') ?>€</span>
                                    <?php endif; ?>
                                    <span><?= number_format($prixFinal, 0, ',', ' ') ?>€</span>
                                </div>
                                <!-- Bouton d'ajout au panier -->
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

    <!-- Pied de page -->
    <footer>
        <div>
            <!-- Liens vers les réseaux sociaux -->
            <div>
                <a href="#"><img src="/html/img/svg/facebook-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/html/img/svg/linkedin-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/html/img/svg/youtube-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/html/img/svg/instagram-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/html/img/svg/tiktok-blank.svg" style="filter: invert(1);"></a>
                <a href="#"><img src="/html/img/svg/pinterest-blank.svg" style="filter: invert(1);"></a>
            </div>

            <!-- Navigation du pied de page -->
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

            <!-- Informations légales -->
            <div>
                <span>Conditions d'utilisation</span>
                <span>Copyright CGRRSC All right reserved</span>
                <span>Condition de ventes</span>
            </div>
        </div>
    </footer>

    <script>
        /**
         * Définit la catégorie sélectionnée et soumet le formulaire
         */
        function definirCategorie(categorie) {
            document.getElementById('champCategorie').value = categorie;
            document.getElementById('filterForm').submit();
        }

        /**
         * Définit la note minimum et soumet le formulaire
         */
        function definirNote(note) {
            document.getElementById('champNote').value = note;
            document.getElementById('filterForm').submit();
        }

        /**
         * Met à jour l'affichage du prix maximum pendant le glissement
         */
        function mettreAJourAffichagePrix(valeur) {
            document.getElementById('affichagePrixMax').textContent = valeur + '€';
        }

        /**
         * Simule l'ajout d'un produit au panier
         */
        function ajouterAuPanier(idProduit) {
            alert('Produit ' + idProduit + ' ajouté au panier !');
        }

        /**
         * Réinitialise tous les filtres en soumettant un formulaire vide
         */
        function reinitialiserFiltres() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            document.body.appendChild(form);
            form.submit();
        }

        /**
         * Active l'édition directe du prix maximum
         */
        function activerEditionPrix() {
            const affichagePrix = document.getElementById('affichagePrixMax');
            const prixActuel = affichagePrix.textContent.replace('€', '');

            // Remplace le span par un input
            const inputPrix = document.createElement('input');
            inputPrix.type = 'number';
            inputPrix.value = prixActuel;
            inputPrix.min = 0;
            inputPrix.max = 3000;
            inputPrix.style.width = '60px';

            affichagePrix.replaceWith(inputPrix);
            inputPrix.focus();
            inputPrix.select();

            // Gère la sauvegarde
            inputPrix.addEventListener('blur', sauvegarderPrix);
            inputPrix.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    sauvegarderPrix();
                }
            });

            function sauvegarderPrix() {
                const nouveauPrix = parseInt(inputPrix.value) || 0;
                const prixValide = Math.min(Math.max(nouveauPrix, 0), 3000);

                // Met à jour le champ caché
                document.querySelector('input[name="price"]').value = prixValide;

                // Recrée le span
                const nouveauSpan = document.createElement('span');
                nouveauSpan.id = 'affichagePrixMax';
                nouveauSpan.textContent = prixValide + '€';
                nouveauSpan.ondblclick = activerEditionPrix;

                inputPrix.replaceWith(nouveauSpan);

                // Soumet le formulaire
                document.getElementById('filterForm').submit();
            }
        }

        /**
         * Initialise les interactions après le chargement de la page
         */
        document.addEventListener('DOMContentLoaded', function () {
            const aside = document.querySelector('aside');

            if (aside) {
                aside.addEventListener('click', function () {
                    this.classList.toggle('open');
                });
            }
        });
    </script>
</body>

</html>