<?php
session_start();

include '../../../selectBDD.php';

include __DIR__ . '../../../fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

if (empty($_SESSION['vendeur_id'])) {
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit(0);
}

$vendeur_id = $_SESSION['vendeur_id'];

function getVendeurInfo($pdo, $vendeur_id) {
    $stmt = $pdo->prepare("SELECT denomination FROM cobrec1._vendeur  WHERE id_vendeur = :id");
    $stmt->execute(['id' => $vendeur_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$vendeurInfos = getVendeurInfo($pdo, $vendeur_id);

//charge les informations du vendeur depuis la base de données
$informationsVendeur = chargerInformationsVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Catalogue vendeur - <?php echo htmlspecialchars($vendeurInfos['denomination'] ?? ''); ?></title>
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

                        <div>
                            <span>
                                <!--affiche les étoiles de notation-->
                                <?php for ($i = 1; $i <= 5; $i++):
                                if ($noteArrondie >= $i)           $s = 'full';
                                elseif ($noteArrondie >= $i - 0.5) $s = 'alf';
                                else                               $s = 'empty';
                            ?>
                                <img src="/img/svg/star-<?= $s ?>.svg" alt="Etoile" width="20">
                                <?php endfor; ?>
                            </span>
                            <!--affiche le nombre d'avis-->
                            <span>(<?= $produitCourant['nombre_avis'] ?? 0 ?>)</span>
                        </div>

                        <p><?= htmlspecialchars($description) ?></p>
                    </div>

                </article>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </main>
</body>

</html>