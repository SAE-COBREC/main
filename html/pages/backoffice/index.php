<?php 
// Démarrage de la session PHP pour accéder aux variables de session
session_start(); 
?>

<?php
// Inclusion du fichier de connexion à la base de données
include '../../selectBDD.php';

// Récupération de l'ID du vendeur connecté depuis la session
if(empty($_SESSION['vendeur_id']) === false){
  $vendeur_id = $_SESSION['vendeur_id'];
}else{
?>
<script>
    alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
    document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
}

// Initialisation d'un tableau vide pour stocker éventuellement de nouveaux articles
$_SESSION['creerArticle'] = [];
$_SESSION['remise'] = [];
$_SESSION['promotion'] = [];
$fichiers = glob('create/temp_/*');
foreach ($fichiers as $value) {
  unlink($value);
}

try {
    // Requête SQL pour récupérer les produits du vendeur
    $query = "
    SELECT DISTINCT on (id_produit)
        p.id_produit,
        p.p_nom AS nom_article,
        p.p_description,
        p.p_stock,
        p.p_prix,
        p.p_statut,
        i.i_lien AS image_url,
        r.reduction_pourcentage AS pourcentage,
        r.reduction_debut AS debut_reduc,
        r.reduction_fin AS fin_reduc,
        promo.promotion_debut AS debut_promo,
        promo.promotion_fin AS fin_promo,
        STRING_AGG(c.nom_categorie, ', ') AS categories
    FROM cobrec1._produit p
    LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit
    LEFT JOIN cobrec1._promotion promo ON p.id_produit = promo.id_produit
    LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
    LEFT JOIN cobrec1._categorie_produit c ON fpd.id_categorie = c.id_categorie
    LEFT JOIN cobrec1._represente_produit rp ON p.id_produit = rp.id_produit
    LEFT JOIN cobrec1._image i ON rp.id_image = i.id_image
    WHERE p.id_vendeur = :id_vendeur
    GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_stock, p.p_prix, pourcentage, debut_reduc, fin_reduc, debut_promo, fin_promo, i.i_lien
    ORDER BY p.id_produit ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . htmlspecialchars($e->getMessage()));
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1440, height=1024" />
  <title>Alizon - Page Accueil Vendeur</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="/styles/AccueilVendeur/accueilVendeur.css" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../../partials/aside.html'; ?>
    
    <main class="main">
      <div class="header">
        <h1 class="header__title">Page accueil vendeur</h1>

        <div class="search-bar">
          <div class="search-bar__input">
            <span class="search-bar__icon"><img src="../../img/svg/loupe.svg" alt="loupe"></span>
            <input type="search" placeholder="Rechercher des produits..." />
          </div>
        </div>
      </div>

      <div class="content-section">
        <div class="content-section__header">
          <h2 class="content-section__title">Articles en lignes</h2>

          <div class="tabs">
            <div class="tabs__item tabs__item--active">------</div>
            <div class="tabs__item">-----</div>
            <div class="tabs__item">---------</div>
            <div class="tabs__item">-------</div>
          </div>

          <div class="filters">
            <div class="filters__item">------- --- --------</div>
            <div class="filters__item">------- -- ------- ▾</div>
            <div class="filters__item">---- -- -------</div>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="products-table">
            <thead>
              <tr>
                <th class="products-table__head-cell col-check"></th>
                <th class="products-table__head-cell col-produit">Produit</th>
                <th class="products-table__head-cell col-statut">Statut</th>
                <th class="products-table__head-cell col-stock">Stock</th>
                <th class="products-table__head-cell col-cate">Catégorie</th>
                <th class="products-table__head-cell col-promo">Promotion</th>
              </tr>
            </thead>
            <tbody>
            <?php if(!empty($articles)): ?>
              <?php foreach ($articles as $article): ?>
                <tr class="products-table__row" data-id="<?php echo $article['id_produit']; ?>">
                  <td class="products-table__cell col-check">
                    <div class="checkbox"></div>
                  </td>
                  <td class="products-table__cell col-produit">
                    <div class="product">
                      <div class="product__image">
                        <img src="<?= str_replace("/img/photo", "../../img/photo", htmlspecialchars($article['image_url'])); ?>" width="60" height="60">
                      </div>
                      <div class="product__info">
                        <h4 class="product__name"><?php echo htmlspecialchars($article['nom_article']); ?></h4>
                        <?php if(!empty($article['pourcentage']) && ((strtotime($article["debut_reduc"]) <= time()) && (time() <= strtotime($article["fin_reduc"])))): ?>
                        <div class="product__prix">
                          <p class="product__model__ancien"><?php echo number_format($article['p_prix'], 2, ',', ' '); ?> €</p>
                          <p class="product__model"><?php echo number_format($article['p_prix']* (1 - $article['pourcentage']/100), 2, ',', ' '); ?> €</p>
                        </div>
                        <?php else: ?>
                          <p class="product__model"><?php echo number_format($article['p_prix'], 2, ',', ' '); ?> €</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="products-table__cell col-statut">
                    <?php if ($article['p_statut'] == 'En ligne'): ?>
                      <?php if ($article['p_stock'] <= 0): ?>
                        <span class="badge badge--out">Épuisé</span>
                      <?php else: ?>
                        <span class="badge badge--live">En ligne</span>
                      <?php endif; ?>
                    <?php elseif ($article['p_statut'] == 'Hors ligne'): ?>
                      <span class="badge badge--hors">Hors ligne</span>
                    <?php elseif ($article['p_statut'] == 'Ébauche'): ?>
                        <span class="badge badge--eb">Ébauche</span>
                    <?php endif; ?>
                  </td>
                  <td class="products-table__cell col-stock"><?php echo htmlspecialchars($article['p_stock']); ?></td>
                  <td class="products-table__cell col-cate"><?php echo htmlspecialchars($article['categories']); ?></td>
                  <td class="products-table__cell col-promo">
                    <?php if(((strtotime($article["debut_promo"]) <= time()) && (time() <= strtotime($article["fin_promo"])))): ?>
                      <p>En Promotion !</p>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          </table>
        </div>
      </div>

      <div class="page-actions">  
        <a href="#" id="btn-promotion" class="btn btn--secondary btn--disabled">Appliquer promotion</a>
        <a href="#" id="btn-remise" class="btn btn--secondary btn--disabled">Appliquer remise</a>
        <a href="#" id="btn-modifier" class="btn btn--secondary btn--disabled">Modifier le produit</a>
        <a href="create/index.php" class="btn btn--primary">Ajouter un produit</a>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const rows = document.querySelectorAll('.products-table__row');
          const btnModifier = document.getElementById('btn-modifier');
          const btnRemise = document.getElementById('btn-remise');
          const btnPromotion = document.getElementById('btn-promotion');

          rows.forEach(row => {
            row.addEventListener('click', () => {
              const isSelected = row.classList.contains('selected');
              const productID = row.dataset.id;
              const checkbox = row.querySelector('.checkbox');

              // Désélectionner toutes les lignes
              rows.forEach(r => {
                r.classList.remove('selected');
                r.querySelector('.checkbox').classList.remove('checkbox--active');
              });

              if (!isSelected) {
                // Sélectionner la ligne cliquée
                row.classList.add('selected');
                checkbox.classList.add('checkbox--active');
                
                // Activer le bouton Modifier
                btnModifier.classList.remove('btn--disabled');
                btnModifier.href = "create/index.php?modifier=" + productID;

                // Activer le bouton Remise
                btnRemise.classList.remove('btn--disabled');
                btnRemise.href = "remise/index.php?modifier=" + productID;

                // Activer le bouton Promotion
                btnPromotion.classList.remove('btn--disabled');
                btnPromotion.href = "promotion/index.php?modifier=" + productID;
              } else {
                // Désactiver le bouton Modifier
                btnModifier.classList.add('btn--disabled');
                btnModifier.href = "#";

                // Désactiver le bouton Remise
                btnRemise.classList.add('btn--disabled');
                btnRemise.href = "#" + productID;

                // Désactiver le bouton Promotion
                btnPromotion.classList.add('btn--disabled');
                btnPromotion.href = "#" + productID;
              }
            });
          });
        });
      </script>
    </main>
  </div>
</body>
</html>