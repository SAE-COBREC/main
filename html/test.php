<?php
include __DIR__ . '/selectBDD.php';

//inclure les fonctions utilitaires
include __DIR__ . '/pages/fonctions.php';

//récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

//définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");


// Récupération de la recherche
$search = $_POST['nomChercher'] ?? '';
$resultats = [];

if (!empty($search)) {
    try {
        $resultats = chercherProduitsNom($connexionBaseDeDonnees, $search);

    } catch (PDOException $e) {
        echo "<p style='color: red;'>Erreur lors de la recherche : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Produits - Cobrec</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    h1 {
        color: #333;
        margin-bottom: 30px;
        text-align: center;
        font-size: 2em;
    }

    .search-form {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
    }

    input[type="search"] {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s;
    }

    input[type="search"]:focus {
        outline: none;
        border-color: #667eea;
    }

    button {
        padding: 12px 30px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s;
    }

    button:hover {
        background: #5568d3;
    }

    .product-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
        transition: transform 0.2s;
    }

    .product-card:hover {
        transform: translateX(5px);
    }

    .product-name {
        font-size: 1.3em;
        color: #333;
        margin-bottom: 10px;
        font-weight: bold;
    }

    .product-description {
        color: #666;
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .product-info {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 0.9em;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .label {
        font-weight: bold;
        color: #667eea;
    }

    .price {
        color: #28a745;
        font-weight: bold;
        font-size: 1.2em;
    }

    .stock {
        color: #17a2b8;
    }

    .status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85em;
    }

    .status.en-ligne {
        background: #d4edda;
        color: #155724;
    }

    .status.hors-ligne {
        background: #f8d7da;
        color: #721c24;
    }

    .rating {
        color: #ffc107;
        font-weight: bold;
    }

    .no-results {
        text-align: center;
        padding: 40px;
        color: #666;
        font-size: 1.1em;
    }

    .count {
        color: #666;
        margin-bottom: 20px;
        font-style: italic;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 Recherche de Produits</h1>

        <form method="POST" class="search-form">
            <input type="search" name="nomChercher"
                placeholder="Rechercher un produit (ex: Smartphone, Pull, Bracelet...)"
                value="<?= htmlspecialchars($search) ?>" autofocus>
            <button type="submit">Rechercher</button>
        </form>

        <?php if (!empty($search)): ?>
        <p class="count">
            <?= count($resultats) ?> résultat<?= count($resultats) > 1 ? 's' : '' ?>
            pour "<?= htmlspecialchars($search) ?>"
        </p>

        <?php if (count($resultats) > 0): ?>
        <?php foreach ($resultats as $produit): ?>
        <div class="product-card">
            <div class="product-name">
                <?= htmlspecialchars($produit['p_nom']) ?>
            </div>

            <div class="product-description">
                <?= htmlspecialchars($produit['p_description']) ?>
            </div>

            <div class="product-info">
                <div class="info-item">
                    <span class="label">Prix:</span>
                    <span class="price"><?= number_format($produit['p_prix'], 2, ',', ' ') ?> €</span>
                </div>

                <div class="info-item">
                    <span class="label">Stock:</span>
                    <span class="stock"><?= $produit['p_stock'] ?> unités</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="no-results">
            Aucun produit trouvé pour votre recherche 😕
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>

</html>