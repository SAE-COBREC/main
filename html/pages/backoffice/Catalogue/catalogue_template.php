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
        <p class="message-vide">Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        <?php else: ?>
        <div class="titre-page">CATALOGUE DE <?= mb_strtoupper(htmlspecialchars($nom_entreprise)) ?></div>
        <div class="informations-entreprise">
            <p>
                <strong>Téléphone:</strong> <?= htmlspecialchars($vendeurInfos['num_telephone'] ?? 'Non spécifié') ?> |
                <strong>Email:</strong> <?= htmlspecialchars($vendeurInfos['email'] ?? 'Non spécifié') ?>
            </p>
        </div>
        <ul>
            <?php 
                $compteur = 1;
                foreach ($listeProduits as $produit): 
                    // Ajouter un titre de page tous les 3 produits
                    if ($compteur % 3 === 1 && $compteur > 1): 
                ?>
        </ul>
        <div class="titre-page" style="page-break-before: always;">CATALOGUE DE
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
                <table class="ligne-produit">
                    <tr>
                        <td class="image-produit" style="text-align: center; vertical-align: middle;">
                            <?php if (!empty($imgSrc)): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= $nom ?>">
                            <?php else: ?>
                            <div class="espace-image"><?= $nom ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="details-produit">
                            <h3><?= $nom ?></h3>
                            <p><?= $description ?></p>
                            <div>
                                <span class="badge-prix">
                                    <span class="etiquette">PRIX</span>
                                    <?= number_format((float)$prix, 2, ',', ' ') ?>€
                                </span>
                            </div>
                            <div class="ligne-info">
                                <span class="etiquette-info">Origine:</span>
                                <span class="valeur-info"><?= $origine ?></span>
                            </div>
                            <div class="ligne-info">
                                <span class="etiquette-info">Catégorie:</span>
                                <span class="valeur-info"><?= $categories ?></span>
                            </div>
                        </td>
                    </tr>
                </table>
                <hr class="separateur">
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