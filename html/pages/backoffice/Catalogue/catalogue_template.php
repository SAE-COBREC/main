<?php
// Charger le CSS compilé depuis le SCSS
$css = file_get_contents(__DIR__ . '/../../../styles/Catalogue/catalogue.css');

ob_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Catalogue vendeur - <?= htmlspecialchars($nom_entreprise) ?></title>
    <style>
    <?=$css ?>
    </style>
</head>

<body>
    <main>
        <?php if (empty($listeProduits)): ?>
        <p class="empty-message">Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        <?php else: ?>
        <div class="page-title">CATALOGUE DE <?= mb_strtoupper(htmlspecialchars($nom_entreprise)) ?></div>
        <div class="company-info">
            <p>
                <strong>Téléphone:</strong> <?= htmlspecialchars($vendeurInfos['num_telephone'] ?? 'Non spécifié') ?> |
                <strong>Email:</strong> <?= htmlspecialchars($vendeurInfos['email'] ?? 'Non spécifié') ?>
            </p>
        </div>
        <ul>
            <?php 
                $compteur = 1;
                foreach ($listeProduits as $produit): 
                    // Ajouter un titre de page tous les 4 produits
                    if ($compteur % 4 === 1 && $compteur > 1): 
                ?>
        </ul>
        <div class="page-title" style="page-break-before: always;">CATALOGUE DE
            <?= mb_strtoupper(htmlspecialchars($nom_entreprise)) ?></div>
        <ul>
            <?php 
                    endif;
                    
                    $nom = htmlspecialchars($produit['p_nom'] ?? 'Sans nom');
                    $description = htmlspecialchars($produit['p_description'] ?? 'Aucune description disponible.');
                    $prix = htmlspecialchars($produit['p_prix'] ?? '0.00');
                    $origine = htmlspecialchars(recupOrigineProduit($connexionBaseDeDonnees, $produit['id_produit']) ?? 'Inconnu');
                    $categories = htmlspecialchars($produit['categories'] ?? 'Inconnue');

                    // Construction du chemin d'image absolu pour DOMPDF
                    $urlImage = $produit['image_url'] ?? '';
                    $urlImage = str_replace('html/img/photo', '/img/photo', $urlImage);
                    $imgPath = $basePath . $urlImage;
                    $imgSrc = '';
                    if (!empty($urlImage) && file_exists($imgPath)) {
                        $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $imgSrc = $imgPath;
                        }
                    }
                ?>
            <li>
                <table class="product-row">
                    <tr>
                        <td class="product-image" style="text-align: center; vertical-align: middle;">
                            <?php if (!empty($imgSrc)): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= $nom ?>">
                            <?php else: ?>
                            <div class="image-placeholder"><?= $nom ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="product-details">
                            <h3><?= $nom ?></h3>
                            <p><?= $description ?></p>
                            <div>
                                <span class="price-badge">
                                    <span class="label">PRIX</span>
                                    <?= number_format((float)$prix, 2, ',', ' ') ?>€
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Origine:</span>
                                <span class="info-value"><?= $origine ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Catégorie:</span>
                                <span class="info-value"><?= $categories ?></span>
                            </div>
                        </td>
                    </tr>
                </table>
                <hr class="separator">
            </li>
            <?php 
                    $compteur++;
                endforeach; 
                ?>
        </ul>
        <?php endif; ?>
    </main>
</body>

</html>
<?php
$html = ob_get_clean();