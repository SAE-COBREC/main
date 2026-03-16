<?php
// Charger le CSS compilé depuis le SCSS pour le style du PDF/catalogue
$css = file_get_contents(__DIR__ . '/../../../styles/Catalogue/catalogue.css');

// Démarrer la temporisation de sortie pour capturer tout le HTML généré
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
        <?php 
        // Vérifier s'il y a des produits à afficher pour ce vendeur
        if (empty($listeProduits)): 
        ?>
        <p class="empty-message">Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        <?php else: ?>
        <!-- En-tête principal de la première page du catalogue -->
        <div class="page-title">CATALOGUE DE <?= mb_strtoupper(htmlspecialchars($nom_entreprise)) ?></div>
        <div class="company-info">
            <p>
                <strong>Téléphone:</strong> <?= htmlspecialchars($vendeurInfos['num_telephone'] ?? 'Non spécifié') ?> |
                <strong>Email:</strong> <?= htmlspecialchars($vendeurInfos['email'] ?? 'Non spécifié') ?>
            </p>
        </div>
        <ul>
            <?php 
                // Initialisation du compteur pour gérer la pagination (4 produits par page)
                $compteur = 1;

                // Parcourir chaque produit du vendeur
                foreach ($listeProduits as $produit): 
                    // Ajouter un saut de page et un nouveau titre tous les 4 produits
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
                    $origine = htmlspecialchars(recupOrigine, requis par les pre-processeurs HTML vers PDF comme DOMPDF
                    $urlImage = $produit['image_url'] ?? '';
                    // Adapter le chemin de l'image pour accéder au dossier local correctement
                    $urlImage = str_replace('html/img/photo', '/img/photo', $urlImage);
                    $imgPath = $basePath . $urlImage;
                    $imgSrc = '';

                    // Vérifier si l'image existe physiquement et si son extension est valideproduit['image_url'] ?? '';
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
                <hr // Incrémenter le compteur pour le prochain produit $compteur++; endforeach; ?>
        </ul>
        <?php endif; // Fin de la condition et de la liste des produits ?>
    </main>
</body>

</html>
<?php
// Récupérer et stocker le contenu HTML final généré dans la variable, puis vider le tampon cache (ob_)y>

</html>
<?php
$html = ob_get_clean();