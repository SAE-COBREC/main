<?php
session_start();

// Récupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;

try {//Récupération des infos de la reduc
    $sql = '
    SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
    WHERE id_panier = : panier;'
    ;
    $stmt = $pdo->prepare($sql);
    $params = [
        'panier' => $id_commande
    ];
    $stmt->execute($params);
    $_SESSION["post-achat"]["facture"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $_SESSION["post-achat"]["facture"] = $_SESSION["post-achat"]["facture"][0];

    $sql = '
    SELECT id_panier, id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA FROM cobrec1._contient
    WHERE id_panier = :panier_commande;'
    ;
    $stmt = $pdo->prepare($sql);
    $params = [
        'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
    ];
    $stmt->execute($params);
    $_SESSION["post-achat"]["contient"] = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $sql = '
    SELECT id_client, timestamp_commande FROM cobrec1._panier_commande
    WHERE id_panier = :panier_commande;'
    ;
    $stmt = $pdo->prepare($sql);
    $params = [
        'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
    ];
    $stmt->execute($params);
    $_SESSION["post-achat"]["panier"] = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
}catch (Exception $e){}




function envoyerCommande($id_commande) {
    $host = '127.0.0.1';
    $port = 9000;
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$fp) {
        return ['success' => false, 'error' => "Transporteur non disponible: $errstr ($errno)", 'bordereau' => null];
    }
    // Envoyer CREATE_LABEL
    $createCmd = "CREATE_LABEL $id_commande\n";
    fwrite($fp, $createCmd);
    // Lire la réponse
    $response = fgets($fp, 256);
    fclose($fp);
    if (preg_match('/LABEL=(\d+)/', $response, $matches)) {
        $bordereau = (int)$matches[1];
        $already = preg_match('/ALREADY_EXISTS=1/', $response);
        $step = 1;
        if (preg_match('/STEP=(\d+)/', $response, $m)) {
            $step = (int)$m[1];
        }
        return ['success' => true, 'bordereau' => $bordereau, 'already' => $already, 'step' => $step];
    }
    return ['success' => false, 'error' => 'Réponse invalide du transporteur', 'bordereau' => null];
}

function getStatusFromSocket($bordereau) {
    $fp = fsockopen('127.0.0.1', 9000, $errno, $errstr, 2);
    if (!$fp) {
        return "Erreur de connexion: $errstr ($errno)";
    }
    fwrite($fp, "STATUS $bordereau\n");
    $response = fgets($fp, 256);
    if (preg_match('/STEP=(\d+)/', $response, $matches)) {
        $response = (int)$matches[1];
    }
    fclose($fp);
    return $response;
}

$resultat = null;
$status = null;
if ($id_commande > 0) {
    if (isset($_SESSION['bordereau']) && $_SESSION['id_commande'] == $id_commande) {
        $bordereau = $_SESSION['bordereau'];
        $resultat = [
            'success' => true,
            'bordereau' => $bordereau,
            'already' => true,
        ];
        $status = getStatusFromSocket($bordereau);
    } else {
        $resultat = envoyerCommande($id_commande);
        if ($resultat && $resultat['success'] && $resultat['bordereau']) {
            $_SESSION['bordereau'] = $resultat['bordereau'];
            $_SESSION['id_commande'] = $id_commande;
            $status = getStatusFromSocket($resultat['bordereau']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Suivi Commande</title>
        <link rel="icon" type="image/png" href="../../img/favicon.svg">
        <link rel="stylesheet" href="/styles/Panier/stylesPanier.css">
        <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
        <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    </head>

    <?php include __DIR__ . '/../../partials/header.php';?>
    <body>
        <h1>Suivi de commande #<?= htmlspecialchars($id_commande) ?></h1>
        <?php if ($resultat): ?>
            <?php if ($resultat['success']): ?>
                <p>Numéro de bordereau : <strong><?= htmlspecialchars($resultat['bordereau']) ?></strong></p>
                <p>Status actuel : <strong id="status-value"><?= $status !== null ? $status : 'Inconnu' ?></strong></p>
            <?php else: ?>
                <p>Erreur : <?= htmlspecialchars($resultat['error']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
                
        <div class="steps">
            <img id="steps" src="../../img/svg//Delivrator/<?= $status !== null ? $status : 1 ?>steps.svg" alt="Box">
        </div>
        <div class="steps2">
            <div>
                <p>Chez Alizon  
                </p>
            </div>
            <div>
                <p>Chez le transporteur
                </p>
            </div>
            <div>
                <p>Sur la plateforme régionale
                </p>
            </div>
            <div>
                <p>Au centre local
                </p>
            </div>
            <div>
                <p>Livré
                </p>
            </div>
        </div>

    </body>
    
    <?php include __DIR__ . '/../../partials/footer.html';?>
</html>
<script>

</script>