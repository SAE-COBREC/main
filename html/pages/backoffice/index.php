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
$fichiers = glob('create/temp_/*');
foreach ($fichiers as $value) {
  unlink($value);
}

try {
    // Requête SQL pour récupérer les produits du vendeur avec leurs catégories et images
    $query = "
    SELECT DISTINCT on (id_produit)
        p.id_produit,
        p.p_nom AS nom_article,
        p.p_description,
        p.p_stock,
        p.p_prix,
        p.p_statut,
        i.i_lien AS image_url,
        STRING_AGG(c.nom_categorie, ', ') AS categories
    FROM cobrec1._produit p
    LEFT JOIN cobrec1._fait_partie_de fpd 
           ON p.id_produit = fpd.id_produit
    LEFT JOIN cobrec1._categorie_produit c 
           ON fpd.id_categorie = c.id_categorie
    LEFT JOIN cobrec1._represente_produit rp
           ON p.id_produit = rp.id_produit
    LEFT JOIN cobrec1._image i
           ON rp.id_image = i.id_image
    WHERE p.id_vendeur = :id_vendeur
    GROUP BY 
        p.id_produit, p.p_nom, p.p_description, p.p_stock, p.p_prix, i.i_lien
    ORDER BY p.id_produit ASC
  ";

    // Préparation de la requête pour éviter les injections SQL
    $stmt = $pdo->prepare($query);
    // Exécution de la requête avec l'ID du vendeur
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    // Récupération de tous les résultats sous forme de tableau associatif
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'erreur de connexion ou de requête, affichage d'un message sécurisé
    die("Erreur de connexion ou de requête : " . htmlspecialchars($e->getMessage()));
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
    <?php
    // Inclusion du menu latéral (aside)
    include __DIR__ . '/../../partials/aside.html';
    ?>
    
    <!-- Contenu principal -->
    <main class="main">
      <div class="header">
        <h1 class="header__title">Page accueil vendeur</h1>

        <div class="search-bar">
          <div class="search-bar__input">
            <span class="search-bar__icon"><img src="../../img/svg/loupe.svg" alt="loupe"></span>
            <input type="search" placeholder="Rechercher des produits..." />
          </div>
          <!-- Bouton pour ajouter un produit -->
          <a href="create/index.php"><button class="btn btn--primary">Ajouter un produit</button></a>
        </div>
      </div>

      <div class="content-section">
        <div class="content-section__header">
          <h2 class="content-section__title">Articles en lignes</h2>

          <!-- Onglets pour filtrer les produits par statut -->
          <div class="tabs">
            <div class="tabs__item tabs__item--active">------</div>
            <div class="tabs__item">-----</div>
            <div class="tabs__item">---------</div>
            <div class="tabs__item">-------</div>
          </div>

          <script>
            // Script pour gérer la sélection des onglets
            const tabs = document.querySelectorAll('.tabs__item');
            tabs.forEach(tab => {
              tab.addEventListener('click', () => {
                // Retire la classe active de tous les onglets
                tabs.forEach(t => t.classList.remove('tabs__item--active'));
                // Ajoute la classe active à l'onglet cliqué
                tab.classList.add('tabs__item--active');
              });
            });
          </script>

          <!-- Filtres supplémentaires -->
          <div class="filters">
            <div class="filters__item">------- --- --------</div>
            <div class="filters__item">------- -- ------- ▾</div>
            <div class="filters__item">---- -- -------</div>
          </div>
        </div>

        <!-- Tableau des produits -->
        <table class="products-table">
          <thead>
            <tr>
              <th class="products-table__head-cell products-table__head-cell--checkbox"></th>
              <th class="products-table__head-cell--produit">Produit</th>
              <th class="products-table__head-cell--statut">Statut</th>
              <th class="products-table__head-cell--stock">Stock</th>
              <th class="products-table__head-cell--cate">Catégorie</th>
              <th class="products-table__head-cell--des">Description</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $article): ?>
              <tr class="products-table__row" data-id="<?php echo $article['id_produit']; ?>">
                <!-- Checkbox pour sélectionner un produit -->
                <td class="products-table__cell--checkbox">
                  <div class="checkbox"></div>
                </td>
                <!-- Colonne produit avec image, nom et prix -->
                <td class="products-table__cell">
                  <div class="product">
                    <div class="product__image">
                      <img src="<?= str_replace("/img/photo", "../../img/photo", htmlspecialchars($article['image_url'])); ?>" width="50" height="50">
                    </div>
                    <div class="product__info">
                      <h4 class="product__name"><?php echo htmlspecialchars($article['nom_article']); ?></h4>
                      <p class="product__model"><?php echo number_format($article['p_prix'], 2, ',', ' '); ?> €</p>
                    </div>
                  </div>
                </td>
                <!-- Statut du produit avec badge -->
                <td class="products-table__cell">
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
                <!-- Stock du produit -->
                <td class="products-table__cell products-table__cell--stock"><?php echo htmlspecialchars($article['p_stock']); ?></td>
                <!-- Catégories -->
                <td class="products-table__cell products-table__cell--catego"><?php echo htmlspecialchars($article['categories']); ?></td>
                <!-- Description -->
                <td class="products-table__cell products-table__cell--descrip"><?php echo htmlspecialchars($article['p_description']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        </table>

        <script>
          // Script pour gérer la sélection d'une ligne et le bouton Ajouter/Modifier
          document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('.products-table__row');
            const addButtonLink = document.querySelector('.search-bar a'); // lien du bouton
            const addButton = document.querySelector('.btn--primary'); // bouton réel

            rows.forEach(row => {
              const checkbox = row.querySelector('.checkbox');

              row.addEventListener('click', () => {
                const isSelected = row.classList.contains('selected');
                const productID = row.dataset.id; // récupère l'id du produit

                if (isSelected) {
                  // Si la ligne était sélectionnée, désélectionner
                  row.classList.remove('selected');
                  checkbox.classList.remove('checkbox--active');

                  addButton.textContent = "Ajouter un produit";
                  addButtonLink.href = "create/index.php"; 
                } else {
                  // Désélectionner toutes les autres lignes
                  rows.forEach(r => {
                    r.classList.remove('selected');
                    r.querySelector('.checkbox').classList.remove('checkbox--active');
                  });

                  // Sélectionner la ligne cliquée
                  row.classList.add('selected');
                  checkbox.classList.add('checkbox--active');

                  // Modifier le bouton et le lien pour "Modifier le produit"
                  addButton.textContent = "Modifier le produit";
                  addButtonLink.href = "create/index.php?modifier=" + productID;
                }
              });
            });
          });
        </script>
      </div>
    </main>
  </div>
</body>
</html>
