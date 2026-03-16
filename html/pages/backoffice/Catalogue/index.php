    <?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../../../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../../fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// VÉRIFICATION DE L'AUTHENTIFICATION VENDEUR
// ============================================

//redirige vers la page de connexion si le vendeur n'est pas connecté
if (!isset($_SESSION['vendeur_id'])) {
    $url = '/pages/backoffice/connexionVendeur/index.php';
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
    exit;
}

// ============================================
// CHARGEMENT DES INFORMATIONS DU VENDEUR
// ============================================

//récupère l'identifiant du vendeur connecté
$idVendeur = $_SESSION['vendeur_id'];
//charge les informations du vendeur depuis la base de données
$informationsVendeur = getVendeurInfo($connexionBaseDeDonnees, $idVendeur);

// ============================================
// CHARGEMENT DES PRODUITS DU CATALOGUE
// ============================================

//récupère les produits déjà en ligne (catalogue actuel)
$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $informationsVendeur['denomination']);

//récupère les produits pas encore en ligne (à ajouter au catalogue)
$produitsHorsCatalogue = ProduitsHorsCatalogue($connexionBaseDeDonnees, $idVendeur);

// ============================================
// FUSION DE TOUS LES PRODUITS
// ============================================

//fusionne les produits hors ligne et en ligne dans une seule liste
$tousProduits = [];

//ajoute les produits hors catalogue avec le marqueur _en_ligne = false
foreach ($produitsHorsCatalogue as $produitCourant) {
    $produitCourant['_en_ligne'] = false;
    $tousProduits[] = $produitCourant;
}

//ajoute les produits en ligne avec le marqueur _en_ligne = true
foreach ($listeProduits as $produitCourant) {
    $produitCourant['_en_ligne'] = true;
    $tousProduits[] = $produitCourant;
}

// ============================================
// FILTRES ET TRI DU CATALOGUE
// ============================================

$rechercheNom = trim($_GET['search'] ?? '');
$filtreStatut = $_GET['statut'] ?? 'all';
$filtreCategorie = $_GET['cat'] ?? 'all';
$triCatalogue = $_GET['tri'] ?? 'nom_asc';

$statutsDisponibles = ['En ligne', 'Hors ligne', 'Ébauche', 'Épuisé'];
$categoriesDisponibles = [];

foreach ($tousProduits as &$produitCourant) {
    $statutAffichage = 'Hors ligne';

    if ($produitCourant['_en_ligne']) {
        $statutAffichage = ((int) ($produitCourant['p_stock'] ?? 0) <= 0) ? 'Épuisé' : 'En ligne';
    } elseif (($produitCourant['p_statut'] ?? '') === 'Ébauche') {
        $statutAffichage = 'Ébauche';
    }

    $produitCourant['_statut_affichage'] = $statutAffichage;

    $listeCategoriesProduit = [];
    $categoriesBrutes = trim((string) ($produitCourant['categories'] ?? ''));

    if ($categoriesBrutes !== '' && $categoriesBrutes !== 'Aucune') {
        $listeCategoriesProduit = array_values(array_filter(array_map('trim', explode(',', $categoriesBrutes))));
        foreach ($listeCategoriesProduit as $categorieCourante) {
            $categoriesDisponibles[$categorieCourante] = true;
        }
    }

    $produitCourant['_categories_liste'] = $listeCategoriesProduit;
}
unset($produitCourant);

$categoriesDisponibles = array_keys($categoriesDisponibles);
natcasesort($categoriesDisponibles);
$categoriesDisponibles = array_values($categoriesDisponibles);

if (!in_array($filtreStatut, array_merge(['all'], $statutsDisponibles), true)) {
    $filtreStatut = 'all';
}

if ($filtreCategorie !== 'all' && !in_array($filtreCategorie, $categoriesDisponibles, true)) {
    $filtreCategorie = 'all';
}

$trisAutorises = ['nom_asc', 'nom_desc', 'prix_asc', 'prix_desc', 'stock_asc', 'stock_desc'];
if (!in_array($triCatalogue, $trisAutorises, true)) {
    $triCatalogue = 'nom_asc';
}

$produitsFiltres = array_values(array_filter($tousProduits, function ($produitCourant) use ($rechercheNom, $filtreStatut, $filtreCategorie) {
    if ($rechercheNom !== '' && stripos((string) ($produitCourant['p_nom'] ?? ''), $rechercheNom) === false) {
        return false;
    }

    if ($filtreStatut !== 'all' && ($produitCourant['_statut_affichage'] ?? '') !== $filtreStatut) {
        return false;
    }

    if ($filtreCategorie !== 'all' && !in_array($filtreCategorie, $produitCourant['_categories_liste'] ?? [], true)) {
        return false;
    }

    return true;
}));

usort($produitsFiltres, function ($produitA, $produitB) use ($triCatalogue) {
    $nomA = strtolower((string) ($produitA['p_nom'] ?? ''));
    $nomB = strtolower((string) ($produitB['p_nom'] ?? ''));
    $prixA = (float) ($produitA['p_prix'] ?? 0);
    $prixB = (float) ($produitB['p_prix'] ?? 0);
    $stockA = (int) ($produitA['p_stock'] ?? 0);
    $stockB = (int) ($produitB['p_stock'] ?? 0);

    switch ($triCatalogue) {
        case 'nom_desc':
            return $nomB <=> $nomA;
        case 'prix_asc':
            return $prixA <=> $prixB;
        case 'prix_desc':
            return $prixB <=> $prixA;
        case 'stock_asc':
            return $stockA <=> $stockB;
        case 'stock_desc':
            return $stockB <=> $stockA;
        case 'nom_asc':
        default:
            return $nomA <=> $nomB;
    }
});

// ============================================
// GESTION DU THÈME D'ACCESSIBILITÉ
// ============================================

//récupère le thème d'accessibilité actuel (daltonisme, etc.)
$themeActuel = $_SESSION['colorblind_mode'] ?? 'default';
?>
    <!doctype html>
    <html lang="fr" <?= ($themeActuel !== 'default') ? 'data-theme="' . htmlspecialchars($themeActuel) . '"' : '' ?>>

    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=1440, height=1024" />
        <title>Catalogue – <?= htmlspecialchars($informationsVendeur['denomination'] ?? '') ?></title>

        <!--charge l'icône du site-->
        <link rel="icon" type="image/png" href="/img/favicon.svg">

        <!--charge les feuilles de style CSS-->
        <link rel="stylesheet" href="/styles/AccueilVendeur/accueilVendeur.css" />
        <link rel="stylesheet" href="/styles/Catalogue/backofficeCatalogue.css" />

        <!--charge le script d'accessibilité-->
        <script src="/js/accessibility.js"></script>
    </head>

    <body>
        <div class="app">
            <?php
        //inclut la barre latérale de navigation
        include __DIR__ . '/../../../partials/aside.html';
        ?>

            <!--conteneur principal de la page catalogue-->
            <main class="main">

                <!--en-tête de la page avec le titre-->
                <div class="header">
                    <h1 class="header__title">Catalogue de
                        <?= htmlspecialchars($informationsVendeur['denomination'] ?? '') ?></h1>
                </div>

                <!--section du contenu principal-->
                <div class="content-section">
                    <!--affiche un message si aucun produit n'existe-->
                    <?php if (empty($tousProduits)): ?>
                    <p style="padding: 20px; color: #666;">Aucun produit pour le moment.</p>
                    <?php else: ?>

                    <!--formulaire du catalogue avec tableau des produits-->
                    <form method="post" id="formCatalogue" action="/pages/backoffice/Catalogue/exportPDF.php"
                        target="_blank">
                        <div class="table-wrapper">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th class="products-table__head-cell col-check">
                                            <button type="button" id="btn-clear-filters" class="filtre__item">
                                                <img src="/img/svg/poubelle.svg" alt="Effacer les filtres" width="20"
                                                    height="20">
                                            </button>
                                        </th>
                                        <th class="products-table__head-cell col-produit">
                                            <input type="search" id="filtre-search" name="search"
                                                placeholder="Rechercher un produit..."
                                                value="<?= htmlspecialchars($rechercheNom) ?>" class="filtre__item" />
                                        </th>
                                        <th class="products-table__head-cell col-statut">
                                            <select id="filtre-statut" name="statut" class="filtre__item">
                                                <option value="all" <?= $filtreStatut === 'all' ? 'selected' : '' ?>>
                                                    Tous les
                                                    statuts
                                                </option>
                                                <?php foreach ($statutsDisponibles as $statutCourant): ?>
                                                <option value="<?= htmlspecialchars($statutCourant) ?>"
                                                    <?= $filtreStatut === $statutCourant ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($statutCourant) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </th>
                                        <th class="products-table__head-cell col-stock">
                                            <select id="filtre-tri" name="tri" class="filtre__item">
                                                <option value="nom_asc"
                                                    <?= $triCatalogue === 'nom_asc' ? 'selected' : '' ?>>A à Z
                                                </option>
                                                <option value="nom_desc"
                                                    <?= $triCatalogue === 'nom_desc' ? 'selected' : '' ?>>Z à A
                                                </option>
                                                <option value="prix_asc"
                                                    <?= $triCatalogue === 'prix_asc' ? 'selected' : '' ?>>Prix
                                                    croissant</option>
                                                <option value="prix_desc"
                                                    <?= $triCatalogue === 'prix_desc' ? 'selected' : '' ?>>Prix
                                                    décroissant</option>
                                                <option value="stock_asc"
                                                    <?= $triCatalogue === 'stock_asc' ? 'selected' : '' ?>>Stock
                                                    croissant</option>
                                                <option value="stock_desc"
                                                    <?= $triCatalogue === 'stock_desc' ? 'selected' : '' ?>>Stock
                                                    décroissant</option>
                                            </select>
                                        </th>
                                        <th class="products-table__head-cell col-cate">
                                            <select id="filtre-cat" name="cat" class="filtre__item">
                                                <option value="all" <?= $filtreCategorie === 'all' ? 'selected' : '' ?>>
                                                    Toutes les
                                                    catégories</option>
                                                <?php foreach ($categoriesDisponibles as $categorieCourante): ?>
                                                <option value="<?= htmlspecialchars($categorieCourante) ?>"
                                                    <?= $filtreCategorie === $categorieCourante ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($categorieCourante) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </th>
                                        <th class="products-table__head-cell col-desc">Origine</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!--boucle sur tous les produits du catalogue-->
                                    <?php if (empty($produitsFiltres)): ?>
                                    <tr>
                                        <td class="products-table__cell" colspan="6">
                                            Aucun produit ne correspond aux filtres.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($produitsFiltres as $produitCourant):
                                    //construit l'URL de l'image du produit
                                    $urlImage = htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg');
                                    //récupère l'origine du produit selon son statut
                                    $origineProduit = $produitCourant['_en_ligne']
                                        ? recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit'])
                                        : ($produitCourant['p_origine'] ?? 'Inconnu');
                                ?>

                                    <!--ligne de produit dans le tableau-->
                                    <tr class="products-table__row"
                                        data-id="<?= (int) $produitCourant['id_produit'] ?>">

                                        <!--case à cocher de sélection-->
                                        <td class="products-table__cell col-check">
                                            <div class="checkbox"></div>
                                            <input type="checkbox" name="produits_selectionnes[]"
                                                value="<?= (int) $produitCourant['id_produit'] ?>" style="display:none;"
                                                class="hidden-checkbox">
                                        </td>

                                        <!--informations du produit (image, nom, prix)-->
                                        <td class="products-table__cell col-produit">
                                            <div class="product">
                                                <div class="product__image">
                                                    <img src="<?= $urlImage ?>" width="60" height="60"
                                                        alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>">
                                                </div>
                                                <div class="product__info">
                                                    <h4 class="product__name">
                                                        <?= htmlspecialchars($produitCourant['p_nom']) ?></h4>
                                                    <p class="product__model">
                                                        <?php echo number_format($produitCourant['p_prix'], 2, ',', ' '); ?>
                                                        €</p>
                                                </div>
                                            </div>
                                        </td>

                                        <!--badge de statut du produit-->
                                        <td class="products-table__cell col-statut">
                                            <?php if (($produitCourant['_statut_affichage'] ?? '') === 'Épuisé'): ?>
                                            <span class="badge badge--out">Épuisé</span>
                                            <?php elseif (($produitCourant['_statut_affichage'] ?? '') === 'En ligne'): ?>
                                            <span class="badge badge--live">En ligne</span>
                                            <?php elseif (($produitCourant['_statut_affichage'] ?? '') === 'Ébauche'): ?>
                                            <span class="badge badge--eb">Ébauche</span>
                                            <?php else: ?>
                                            <span class="badge badge--hors">Hors ligne</span>
                                            <?php endif; ?>
                                        </td>

                                        <!--stock du produit-->
                                        <td class="products-table__cell col-stock">
                                            <?= (int) $produitCourant['p_stock'] ?>
                                        </td>

                                        <!--catégorie du produit-->
                                        <td class="products-table__cell col-cate">
                                            <?= htmlspecialchars($produitCourant['categories'] ?? 'Aucune') ?>
                                        </td>

                                        <!--origine du produit-->
                                        <td class="products-table__cell col-desc">
                                            <?= htmlspecialchars($origineProduit ?? 'Inconnu') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!--bouton d'export PDF en bas du tableau-->
                        <div class="page-actions">
                            <button type="button" class="btn btn--secondary" id="btn-select-all">Tout
                                sélectionner</button>
                            <button type="submit" class="btn btn--primary btn--disabled" id="btn-export-pdf"
                                style="margin-right: 20px;" disabled>
                                Exporter en PDF
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                </div>
            </main>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            //sélectionne toutes les lignes du tableau
            var lignes = document.querySelectorAll('.products-table__row');
            //sélectionne le bouton d'export PDF
            var boutonExportPdf = document.getElementById('btn-export-pdf');
            //sélectionne le bouton tout sélectionner
            var boutonSelectAll = document.getElementById('btn-select-all');

            //met à jour l'état du bouton d'export selon la sélection
            function mettreAJourBoutons() {
                var lignesSelectionnees = document.querySelectorAll('.products-table__row.selected');
                if (boutonExportPdf) {
                    if (lignesSelectionnees.length > 0) {
                        boutonExportPdf.classList.remove('btn--disabled');
                        boutonExportPdf.disabled = false;
                    } else {
                        boutonExportPdf.classList.add('btn--disabled');
                        boutonExportPdf.disabled = true;
                    }
                }
                if (boutonSelectAll) {
                    if (lignesSelectionnees.length === lignes.length) {
                        boutonSelectAll.textContent = 'Tout désélectionner';
                    } else {
                        boutonSelectAll.textContent = 'Tout sélectionner';
                    }
                }
            }

            //ajoute un écouteur de clic sur chaque ligne pour la sélection
            lignes.forEach(function(ligne) {
                ligne.addEventListener('click', function() {
                    var caseACocherInput = ligne.querySelector('.hidden-checkbox');
                    var caseACocherDiv = ligne.querySelector('.checkbox');

                    //bascule la sélection de la ligne
                    ligne.classList.toggle('selected');
                    caseACocherDiv.classList.toggle('checkbox--active');
                    caseACocherInput.checked = !caseACocherInput.checked;

                    //met à jour l'état des boutons
                    mettreAJourBoutons();
                });
            });

            //ajoute un écouteur de clic sur le bouton tout sélectionner
            if (boutonSelectAll) {
                boutonSelectAll.addEventListener('click', function() {
                    var allSelected = document.querySelectorAll('.products-table__row.selected')
                        .length === lignes.length;
                    lignes.forEach(function(ligne) {
                        var caseACocherInput = ligne.querySelector('.hidden-checkbox');
                        var caseACocherDiv = ligne.querySelector('.checkbox');
                        if (allSelected) {
                            // désélectionner tout
                            ligne.classList.remove('selected');
                            caseACocherDiv.classList.remove('checkbox--active');
                            caseACocherInput.checked = false;
                        } else {
                            // sélectionner tout
                            ligne.classList.add('selected');
                            caseACocherDiv.classList.add('checkbox--active');
                            caseACocherInput.checked = true;
                        }
                    });
                    mettreAJourBoutons();
                });
            }

            var formulaireCatalogue = document.getElementById('formCatalogue');
            if (formulaireCatalogue) {
                formulaireCatalogue.addEventListener('submit', function(event) {
                    var lignesSelectionnees = document.querySelectorAll(
                        '.products-table__row.selected');
                    if (lignesSelectionnees.length === 0) {
                        event.preventDefault();
                    }
                });
            }

            // ---- synchronisation des filtres ----
            function appliquerFiltres() {
                var params = new URLSearchParams();
                var search = document.getElementById('filtre-search');
                var statut = document.getElementById('filtre-statut');
                var tri = document.getElementById('filtre-tri');
                var cat = document.getElementById('filtre-cat');
                if (search && search.value.trim() !== '') params.set('search', search.value.trim());
                if (statut && statut.value !== 'all') params.set('statut', statut.value);
                if (tri && tri.value !== 'nom_asc') params.set('tri', tri.value);
                if (cat && cat.value !== 'all') params.set('cat', cat.value);
                window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() :
                    '');
            }

            ['filtre-statut', 'filtre-tri', 'filtre-cat'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', appliquerFiltres);
            });

            var boutonEffacerFiltres = document.getElementById('btn-clear-filters');
            if (boutonEffacerFiltres) {
                boutonEffacerFiltres.addEventListener('click', function() {
                    window.location.href = window.location.pathname;
                });
            }

            var searchInput = document.getElementById('filtre-search');
            if (searchInput) {
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        appliquerFiltres();
                    }
                });
            }
        });
        </script>
    </body>

    </html>