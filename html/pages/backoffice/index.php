<?php session_start(); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1440, height=1024" />
  <title>Alizon - Page Accueil Vendeur</title>
  <link rel="stylesheet" href="/html/styles/AccueilVendeur/accueilVendeur.css" />
</head>
<body>
  <?php
    include '../../selectBDD.php';

    // ID du vendeur connecté (à adapter dynamiquement via la session)
    $vendeur_id = 2;

    try {
    // Récupération des produits depuis la BDD avec leurs catégories
    $query = "
        SELECT 
            p.id_produit,
            p.p_nom AS nom_article,
            p.p_description,
            p.p_stock,
            p.p_prix,
            i.i_lien AS image_url,
            c.nom_categorie AS categorie
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
        ORDER BY p.id_produit ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Erreur de connexion ou de requête : " . htmlspecialchars($e->getMessage()));
    }

  ?>
  <div class="app">
    <?php
    include __DIR__ . '../../partials/aside.html';
    ?>
    
    <!-- Main Content -->
    <main class="main">
      <div class="header">
        <h1 class="header__title">Page accueil vendeur</h1>

        <div class="search-bar">
          <div class="search-bar__input">
            <span class="search-bar__icon"><img src="../../img/svg/loupe.svg" alt="loupe"></span>
            <input type="search" placeholder="Rechercher des produits..." />
          </div>
          <a href="create/index.php"><button class="btn btn--primary">Ajouter un produit</button></a>
        </div>
      </div>

      <div class="content-section">
        <div class="content-section__header">
          <h2 class="content-section__title">Articles en lignes</h2>

          <div class="tabs">
            <div class="tabs__item tabs__item--active">Toutes</div>
            <div class="tabs__item">Actif</div>
            <div class="tabs__item">Brouillon</div>
            <div class="tabs__item">Archivé</div>
          </div>
          <script>
            // Sélectionne tous les éléments avec la classe .tabs__item
            const tabs = document.querySelectorAll('.tabs__item');

            tabs.forEach(tab => {
              tab.addEventListener('click', () => {
                // Retire la classe active sur tous les onglets
                tabs.forEach(t => t.classList.remove('tabs__item--active'));

                // Ajoute la classe active sur celui cliqué
                tab.classList.add('tabs__item--active');
              });
            });
          </script>

          <div class="filters">
            <div class="filters__item">Filtrer les produits</div>
            <div class="filters__item">Vendeur du produit ▾</div>
            <div class="filters__item">Plus de filtres</div>
          </div>
        </div>

        <table class="products-table">
          <thead>
            <tr>
              <th class="products-table__head-cell products-table__head-cell--checkbox"></th>
              <th class="products-table__head-cell">Produit</th>
              <th class="products-table__head-cell">Statut</th>
              <th class="products-table__head-cell">Stock</th>
              <th class="products-table__head-cell">Catégorie</th>
              <th class="products-table__head-cell">Description</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $article): ?>
              <tr class="products-table__row">
                <td class="products-table__cell">
                  <div class="checkbox"></div>
                </td>
                <td class="products-table__cell">
                  <div class="product">
                    <div class="product__image">
                      <img src="<?php echo htmlspecialchars($article['image_url']); ?>" width="50" height="50" alt="<?php echo htmlspecialchars($article['nom_article']); ?>">
                    </div>
                    <div class="product__info">
                      <h4 class="product__name"><?php echo htmlspecialchars($article['nom_article']); ?></h4>
                      <p class="product__model"><?php echo number_format($article['p_prix'], 2, ',', ' '); ?> €</p>
                    </div>
                  </div>
                </td>
                <td class="products-table__cell">
                  <?php if ($article['p_stock'] > 0): ?>
                    <span class="badge badge--live">En ligne</span>
                  <?php else: ?>
                    <span class="badge badge--out">Épuisé</span>
                  <?php endif; ?>
                </td>
                <td class="products-table__cell products-table__cell--stock"><?php echo htmlspecialchars($article['p_stock']); ?></td>
                <td class="products-table__cell products-table__cell--catego"><?php echo htmlspecialchars($article['categorie']); ?></td>
                <td class="products-table__cell products-table__cell--descrip"><?php echo htmlspecialchars($article['p_description']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align:center;">Aucun article pour ce vendeur</td>
            </tr>
          <?php endif; ?>
        </tbody>
        </table>
        <script>
          document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('.products-table__row');
            const addButton = document.querySelector('.btn--primary');

            rows.forEach(row => {
              const checkbox = row.querySelector('.checkbox');

              row.addEventListener('click', () => {
                const isSelected = row.classList.contains('selected');

                // Si déjà sélectionné → on désélectionne tout
                if (isSelected) {
                  row.classList.remove('selected');
                  checkbox.classList.remove('checkbox--active');
                  addButton.textContent = "Ajouter un produit"; // revenir à l'état initial
                } else {
                  // Sinon on désélectionne les autres
                  rows.forEach(r => {
                    r.classList.remove('selected');
                    r.querySelector('.checkbox').classList.remove('checkbox--active');
                  });

                  // Et on sélectionne celui-ci
                  row.classList.add('selected');
                  checkbox.classList.add('checkbox--active');
                  addButton.textContent = "Modifier le produit"; // changer le texte
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