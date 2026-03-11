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

$vendeur_produits = ProduitDenominationVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Catalogue vendeur - <?php echo htmlspecialchars($vendeurInfos['denomination'] ?? ''); ?></title>
</head>

<body>
    <header>
        <!--carte de profil du vendeur-->
        <section>

            <!--affiche l'avatar du vendeur ou un placeholder-->
            <?php if (!empty($informationsVendeur['image'])): ?>
            <img src="<?= htmlspecialchars($informationsVendeur['image']) ?>"
                alt="<?= htmlspecialchars($informationsVendeur['denomination']) ?>">
            <?php else: ?>
            <figure>
                <img src="/img/svg/market.svg" alt="Vendeur">
            </figure>
            <?php endif; ?>

            <!--informations textuelles du vendeur-->
            <div>

                <!--nom de la dénomination du vendeur-->
                <h1><?= htmlspecialchars($informationsVendeur['denomination']) ?></h1>

                <!--affiche la raison sociale si elle existe-->
                <?php if (!empty($informationsVendeur['raison_sociale'])): ?>
                <small><?= htmlspecialchars($informationsVendeur['raison_sociale']) ?></small>
                <?php endif; ?>

                <!--affiche la note moyenne si elle existe-->
                <?php if ($noteMoyenneVendeur > 0): ?>
                <p>
                    <!--affiche les étoiles de la note moyenne-->
                    <?= afficherEtoilesVendeur($noteMoyenneVendeur, 18) ?>
                    <!--affiche la valeur numérique de la note-->
                    <strong><?= number_format($noteMoyenneVendeur, 1, ',', '') ?>/5</strong>
                    <!--affiche le nombre d'avis-->
                    <span style="color:#9ca3af">(<?= $nombreAvisTotal ?> avis)</span>
                </p>
                <?php endif; ?>

                <!--affiche la localisation du vendeur si elle existe-->
                <?php if (!empty($informationsVendeur['ville'])): ?>
                <address>
                    <img src="/img/svg/location.svg" alt="" width="14" onerror="this.style.display='none'">
                    <!--affiche le code postal s'il est disponible-->
                    <?php if (!empty($informationsVendeur['code_postal'])): ?>
                    <?= htmlspecialchars($informationsVendeur['code_postal']) ?> –
                    <?php endif; ?>
                    <?= htmlspecialchars($informationsVendeur['ville']) ?>
                </address>
                <?php endif; ?>

                <!--chips de statistiques rapides-->
                <footer>
                    <!--chip affichant le nombre de produits en ligne-->
                    <span>
                        <img src="/img/svg/market.svg" alt="">
                        <?= $nombreProduits ?> produit<?= $nombreProduits > 1 ? 's' : '' ?> en ligne
                    </span>
                    <!--affiche le numéro SIREN si disponible-->
                    <?php if (!empty($informationsVendeur['siren'])): ?>
                    <span>
                        SIREN : <?= htmlspecialchars($informationsVendeur['siren']) ?>
                    </span>
                    <?php endif; ?>
                </footer>

            </div>
        </section>
    </header>

    <main>
    </main>
</body>

</html>