<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../../../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../../fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

$vendeur_id = $_SESSION['vendeur_id'];

$vendeurInfos = getVendeurInfo($pdo, $vendeur_id);

$listeIdProduit = $_POST['produits_selectionnes'] ?? [];
$listeProduits = [];

foreach ($listeIdProduit as $idProduit) {
    $produit = getProduitParId($connexionBaseDeDonnees, (int)$idProduit, (int)$vendeur_id);
    if (!empty($produit)) {
        $listeProduits[] = $produit;
    }
}

function getProduitParId($pdo, $idProduit, $vendeurId) {
    $reqDenomination = "
        SELECT DISTINCT ON (p.id_produit)
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_nb_ventes,
            r.reduction_pourcentage,
            t.montant_tva as tva,
            pr.id_produit as estEnpromo,
            (SELECT COUNT(*) FROM cobrec1._avis av2 WHERE av2.id_produit = p.id_produit AND av2.a_note IS NOT NULL) as nombre_avis,
            (SELECT ROUND(COALESCE(AVG(av3.a_note), 0)::numeric, 1) FROM cobrec1._avis av3 WHERE av3.id_produit = p.id_produit AND av3.a_note IS NOT NULL) as note_moyenne,
            (SELECT COALESCE(i2.i_lien, '/img/photo/smartphone_xpro.jpg') FROM cobrec1._represente_produit rp2 LEFT JOIN cobrec1._image i2 ON rp2.id_image = i2.id_image WHERE rp2.id_produit = p.id_produit LIMIT 1) as image_url,
            STRING_AGG(DISTINCT cp.nom_categorie, ', ') as categories,
            v.denomination,
            v.raison_sociale AS vendeur_nom
        FROM cobrec1._produit p
        INNER JOIN cobrec1._vendeur v ON p.id_vendeur = v.id_vendeur
        LEFT JOIN cobrec1._reduction r ON p.id_produit = r.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN r.reduction_debut AND r.reduction_fin
        LEFT JOIN cobrec1._promotion pr ON p.id_produit = pr.id_produit 
            AND CURRENT_TIMESTAMP BETWEEN pr.promotion_debut AND pr.promotion_fin
        LEFT JOIN cobrec1._tva t ON p.id_tva = t.id_tva
        LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        WHERE p.id_produit = :idProduit
            AND p.id_vendeur = :vendeurId
        GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, 
                 p.p_statut, r.reduction_pourcentage, pr.id_produit, t.montant_tva,
                 v.denomination, v.raison_sociale
        ORDER BY p.id_produit
    ";
    
    $stmt = $pdo->prepare($reqDenomination);
    $stmt->execute([
        'idProduit' => $idProduit,
        'vendeurId' => $vendeurId
    ]);
    $donnees = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $donnees;
}


echo "<pre>";
print_r($listeProduits);
echo "</pre>";

?>



<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Catalogue vendeur - <?php echo htmlspecialchars($vendeurInfos['denomination'] ?? ''); ?></title>
    <link rel="stylesheet" href="../../../styles/Catalogue/catalogue.css">
</head>

<body>
    <header>
        <h1>Catalogue de <?php echo htmlspecialchars($vendeurInfos['denomination'] ?? ''); ?></h1>
    </header>

    <main>
        <!--affiche un message si le vendeur n'a aucun produit-->
        <?php if (empty($listeProduits)): ?>
        <p>Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        <?php else: ?>

        <!--grille des produits du vendeur-->
        <ul>
            <!--boucle sur tous les produits du vendeur-->
            <?php foreach ($listeProduits as $produitCourant):
                //arrondit la note moyenne
                $noteArrondie = (int) round($produitCourant['note_moyenne'] ?? 0);
                //construit l'URL de l'image du produit
                $urlImage = str_replace('html/img/photo', '/img/photo', $produitCourant['image_url'] ?? '/img/default-product.jpg');

                $description = $produitCourant['p_description'] ?? 'Aucune description disponible.';
                $prix = $produitCourant['p_prix'] ?? '';

                //récupère l'origine du produit
                $origineProduit = recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
            ?>
            <li>
                <article>
                    <div>
                        <!--image du produit-->
                        <img src="<?= htmlspecialchars($urlImage) ?>"
                            alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>">
                    </div>

                    <div>
                        <!--nom du produit-->
                        <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>

                        <p><?= htmlspecialchars($description) ?></p>

                        <div>
                            <span>
                                <span>PRIX</span>
                                <span><?= number_format($prix, 2, ',', ' ') ?>€</span>
                            </span>
                        </div>

                        <section>
                            <span>Origine:</span>
                            <div>
                                <span> <?= htmlspecialchars($origineProduit) ?></span>
                            </div>
                        </section>

                        <section>
                            <span>Categorie:</span>
                            <div>
                                <span> <?= htmlspecialchars($produitCourant['categories'] ?? 'Inconnue') ?></span>
                            </div>
                        </section>
                    </div>

                </article>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </main>
</body>

</html>