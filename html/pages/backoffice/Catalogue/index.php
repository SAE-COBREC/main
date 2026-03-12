<?php
session_start();

include '../../../selectBDD.php';

include __DIR__ . '../../../fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

//vérifier si le vendeur est connecté via la session
if (!isset($_SESSION['vendeur_id'])) {
    $url = '/pages/backoffice/connexionVendeur/index.php';
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '">';
    exit;
}

$vendeur_id = $_SESSION['vendeur_id'];

$vendeurInfos = getVendeurInfo($pdo, $vendeur_id);

//charge les informations du vendeur depuis la base de données
$informationsVendeur = chargerInformationsVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Acceuil Catalogue vendeur - <?php echo htmlspecialchars($vendeurInfos['denomination'] ?? ''); ?></title>
    <link rel="stylesheet" href="../../../styles/Catalogue/catalogue.css">
</head>