<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1440, height=1024" />
  <title>Alizon - Page Accueil Vendeur</title>
  <link rel="stylesheet" href="/html/styles/AccueilVendeur/accueilVendeur.css" />
</head>
<?php include '../../../../config.php';?>
<body>
  <?php
    // ID du vendeur à afficher
    $vendeur_id = 101;

    // Tableau pour stocker les articles
    $articles = [];

    // Lecture du fichier CSV
    if (($handle = fopen("../../../src/data/articles_vendeur.csv", "r")) !== false) {
        $header = fgetcsv($handle, 1000, ";"); // lire l'en-tête
        while (($data = fgetcsv($handle, 1000, ";")) !== false) {
            $article = array_combine($header, $data);
            if ($article['id_vendeur'] == $vendeur_id) {
                $articles[] = $article;
            }
        }
        fclose($handle);
    }
    ?>   
  <div class="app">
    <?php
    include __DIR__ . '/../../partials/aside.html';
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
          <button class="btn btn--primary">Ajouter un produit</button>
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

                  </td>
                  <td class="products-table__cell">
                    <div class="product">
                      <div class="product__image">
                        <img src="<?php echo htmlspecialchars($article['image_url']) ?>" width="50" height="50" alt="<?php echo htmlspecialchars($article['nom_article']); ?>">
                      </div>
                      <div class="product__info">
                        <h4 class="product__name"><?php echo htmlspecialchars($article['nom_article']); ?></h4>
                        <p class="product__model"><?php echo number_format($article['prix'], 2, ',', ' '); ?> €</p>
                      </div>
                    </div>
                  </td>
                  <td class="products-table__cell">
                    <?php if ($article['stock'] > 0): ?>
                      <span class="badge badge--live">En ligne</span>
                    <?php else: ?>
                      <span class="badge badge--out">Épuisé</span>
                    <?php endif; ?>
                  </td>
                  <td class="products-table__cell products-table__cell--stock"><?php echo htmlspecialchars($article['stock']); ?></td>
                  <td class="products-table__cell products-table__cell--catego"><?php echo htmlspecialchars($article['categorie']); ?></td>
                  <td class="products-table__cell products-table__cell--descrip"><?php echo htmlspecialchars($article['description']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align:center;">Aucun article</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>