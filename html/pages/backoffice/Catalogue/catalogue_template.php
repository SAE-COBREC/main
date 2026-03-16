<?php
/**
 * Template HTML du catalogue PDF.
 * 
 * Variables attendues :
 *   $nom_entreprise  — string, nom du vendeur
 *   $listeProduits   — array, liste des produits
 *   $basePath         — string, chemin absolu vers html/
 *   $connexionBaseDeDonnees — PDO
 */

// Charger le CSS compilé depuis le SCSS
$css = file_get_contents(__DIR__ . '/catalogue_pdf.css');

// Démarrage de la construction HTML
$html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Catalogue vendeur - ' . htmlspecialchars($nom_entreprise) . '</title>
    <style>' . $css . '</style>
</head>
<body>
    <header>
        <h1>Catalogue de ' . htmlspecialchars($nom_entreprise) . '</h1>
    </header>
    <main>';

if (empty($listeProduits)) {
    $html .= '<p class="empty-message">Ce vendeur n\'a aucun produit en ligne pour le moment.</p>';
} else {
    $html .= '<ul>';
    foreach ($listeProduits as $produit) {
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

        $html .= '<li>
            <table class="product-row">
                <tr>
                    <td class="product-image">';
        if (!empty($imgSrc)) {
            $html .= '<img src="' . htmlspecialchars($imgSrc) . '" alt="' . $nom . '">';
        }
        $html .= '</td>
                    <td class="product-details">
                        <h3>' . $nom . '</h3>
                        <p>' . $description . '</p>
                        <div>
                            <span class="price-badge">
                                <span class="label">PRIX</span>
                                ' . $prix . '€
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Origine:</span>
                            <span class="info-value">' . $origine . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Catégorie:</span>
                            <span class="info-value">' . $categories . '</span>
                        </div>
                    </td>
                </tr>
            </table>
            <hr class="separator">
        </li>';
    }
    $html .= '</ul>';
}

$html .= '
    </main>
</body>
</html>';
