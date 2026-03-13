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
if (!isset($_SESSION['idClient'])) {
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
                <div class="content-section__header">
                    <h2 class="content-section__title">Gérer le catalogue</h2>
                </div>

                <!--affiche un message si aucun produit n'existe-->
                <?php if (empty($tousProduits)): ?>
                <p style="padding: 20px; color: #666;">Aucun produit pour le moment.</p>
                <?php else: ?>

                <!--formulaire du catalogue avec tableau des produits-->
                <form method="post" id="formCatalogue" action="test.php" target="_blank">
                    <div class="table-wrapper">
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th class="products-table__head-cell col-check"></th>
                                    <th class="products-table__head-cell col-produit">Produit</th>
                                    <th class="products-table__head-cell col-statut">Statut</th>
                                    <th class="products-table__head-cell col-stock">Stock</th>
                                    <th class="products-table__head-cell col-cate">Catégorie</th>
                                    <th class="products-table__head-cell col-desc">Origine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!--boucle sur tous les produits du catalogue-->
                                <?php foreach ($tousProduits as $produitCourant):
                                    //construit l'URL de l'image du produit
                                    $urlImage = htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg');
                                    //récupère l'origine du produit selon son statut
                                    $origineProduit = $produitCourant['_en_ligne']
                                        ? recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit'])
                                        : ($produitCourant['p_origine'] ?? 'Inconnu');
                                ?>

                                <!--ligne de produit dans le tableau-->
                                <tr class="products-table__row" data-id="<?= (int) $produitCourant['id_produit'] ?>">

                                    <!--case à cocher de sélection-->
                                    <td class="products-table__cell col-check">
                                        <div class="checkbox" style="border-radius: 0;"></div>
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
                                                    <?= htmlspecialchars($produitCourant['p_prix']) ?> €</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!--badge de statut du produit-->
                                    <td class="products-table__cell col-statut">
                                        <?php if ($produitCourant['_en_ligne']): ?>
                                        <?php if ($produitCourant['p_stock'] <= 0): ?>
                                        <span class="badge badge--out">Épuisé</span>
                                        <?php else: ?>
                                        <span class="badge badge--live">En ligne</span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <?php if (($produitCourant['p_statut'] ?? '') === 'Ébauche'): ?>
                                        <span class="badge badge--eb">Ébauche</span>
                                        <?php else: ?>
                                        <span class="badge badge--hors">Hors ligne</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <!--stock du produit-->
                                    <td class="products-table__cell col-stock"><?= (int) $produitCourant['p_stock'] ?>
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
                            </tbody>
                        </table>
                    </div>

                    <!--bouton d'export PDF en bas du tableau-->
                    <div class="page-actions">
                        <button type="submit" class="btn btn--primary btn--disabled" id="btn-export-pdf" disabled
                            style="margin-right: 20px;">
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

        var formulaireCatalogue = document.getElementById('formCatalogue');
        if (formulaireCatalogue) {
            formulaireCatalogue.addEventListener('submit', function(event) {
                var lignesSelectionnees = document.querySelectorAll('.products-table__row.selected');
                if (lignesSelectionnees.length === 0) {
                    event.preventDefault();
                }
            });
        }
    });
    </script>
</body>

</html>