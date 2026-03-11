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
    $stmt = $pdo->prepare("SELECT denomination, SIREN, nb_produits_crees FROM cobrec1._vendeur  WHERE id_vendeur = :id");
    $stmt->execute(['id' => $vendeur_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$vendeurInfos = getVendeurInfo($pdo, $vendeur_id);

$vendeur_produits = ProduitDenominationVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination'] ?? '');
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
        <p>SIREN : <?php echo htmlspecialchars($vendeurInfos['SIREN'] ?? ''); ?></p>
    </header>

    <main>
    </main>
</body>

</html>