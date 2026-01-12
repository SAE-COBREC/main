<?php 
// Démarrage de la session PHP pour accéder aux variables de session
session_start(); 
?>

<?php
// Inclusion de fichier 
include '../../selectBDD.php';
include __DIR__ . '../../fonctions.php';

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
    $tri = $_GET['tri'] ?? 'id_asc';
    $order_sql = "id_produit ASC";

    if($tri === 'prix_asc'){
      $order_sql = "p_prix ASC";
    }elseif($tri === 'prix_desc'){
      $order_sql = "p_prix DESC";
    }
    
    // Requête SQL pour récupérer les produits du vendeur
    $query = "
    SELECT * FROM (
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
    ) AS subquery
     ORDER BY $order_sql";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $articles_bruts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tous_les_statuts = ['En ligne', 'Hors ligne', 'Ébauche', 'Épuisé'];

    $filtres_actifs = isset($_GET['st']) ? explode(',', $_GET['st']) : $tous_les_statuts;

    if(isset($_GET['st']) && $_GET['st'] === 'aucun'){
      $filtres_actifs = [];
    }

    $tout_est_selectionne = (count($filtres_actifs) === count($tous_les_statuts));

    $articles = array_filter($articles_bruts, function($article) use ($filtres_actifs){
      $statut_reel = $article['p_statut'];
      if($article['p_statut'] == 'En ligne' && $article['p_stock'] <= 0){
        $statut_reel = 'Épuisé';
      }
      return in_array($statut_reel, $filtres_actifs);
    });

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
            <div class="tabs__item <?= $tout_est_selectionne ? 'tabs__item--active' : '' ?>" id="tab-all">
              Tous les articles
            </div>
            <?php foreach ($tous_les_statuts as $label): ?>
              <?php $est_actif = in_array($label, $filtres_actifs); ?>
                <div class="tabs__item <?= $est_actif ? 'tabs__item--active' : '' ?> tab-status" 
                    data-label="<?= $label ?>">
                    <?= $label ?>
                </div>
            <?php endforeach; ?>
        </div>
          <div class="tri">
            <div class="tri__item"><span class="tri__label">Trier par prix :</span>
              <a href="<?= genererUrlTri('prix_asc') ?>" 
                class="tri__item <?= ($tri === 'prix_asc') ? 'tri__item--active' : '' ?>">
                Croissant
              </a>
              <a href="<?= genererUrlTri('prix_desc') ?>" 
                class="tri__item <?= ($tri === 'prix_desc') ? 'tri__item--active' : '' ?>">
                Décroissant
              </a>
              <?php if($tri !== 'id_asc'): ?>
                  <a href="<?= genererUrlTri('id_asc') ?>" class="tri__item" style="color: #666; font-size: 0.8em;">Réinitialiser</a>
              <?php endif; ?>
            </div>
            <div class="tri__item">------- -- -------</div>
            <div class="tri__item">---- -- -------</div>
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
                    <?php if(!empty($article['debut_promo']) && ((strtotime($article["debut_promo"]) <= time()) && (time() <= strtotime($article["fin_promo"])))): ?>
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
          const tabAll = document.getElementById('tab-all');
          const tabsStatuts = document.querySelectorAll('.tab-status');

          tabAll.addEventListener('click', () => {
            const isCurrentlyActive = tabAll.classList.contains('tabs__item--active');
            let url = "";

            if(!isCurrentlyActive){
              const tous = Array.from(tabsStatuts).map(t => t.getAttribute('data-label'));
              url = "?st=" + tous.join(',');
            }else{
              url = "?st=aucun";
            }
            window.location.href = url;
          });

          tabsStatuts.forEach(tab => {
            tab.addEventListener('click', () => {
              tab.classList.toggle('tabs__item--active');
            
              const actifs = Array.from(document.querySelectorAll('.tab-status.tabs__item--active')).map(t => t.getAttribute('data-label'));

              if(actifs.length > 0){
                window.location.href = "?st=" + actifs.join(',');
              }else{
                window.location.href = "?st=aucun";
              }
            });
          });
        });
      </script>
    </main>
  </div>
</body>
</html>