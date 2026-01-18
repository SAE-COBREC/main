<?php
session_start();

// Recupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;


// Ouvre une connexion socket et s'authentifie automatiquement

function connectAndLogin($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$fp) {
        return ['fp' => false, 'error' => "Transporteur non disponible: $errstr ($errno)"];
    }

    // LOGIN au serveur
    fwrite($fp, "LOGIN Alizon mdp\n");
    $loginResponse = fgets($fp, 256);

    // Vérifier si le login a réussi
    if (strpos($loginResponse, 'LOGIN_SUCCESS') === false) {
        fclose($fp);
        return ['fp' => false, 'error' => "Échec authentification transporteur: $loginResponse"];
    }
    return ['fp' => $fp, 'error' => null];
}

function envoyerCommande($id_commande) {
    $host = '10.253.5.101';
    $port = 9000;
    $conn = connectAndLogin($host, $port);
    if (!$conn['fp']) {
        return ['success' => false, 'error' => $conn['error'], 'bordereau' => null];
    }
    $fp = $conn['fp'];

    // 2. Envoyer CREATE_LABEL
    $createCmd = "CREATE_LABEL $id_commande\n";
    fwrite($fp, $createCmd);

    // Gestion bloquaga
    stream_set_timeout($fp, 2); 
    $response = fgets($fp, 256);
    
    // Vérifier si serv a bloquer l'acces
    $info = stream_get_meta_data($fp);
    if ($info['timed_out']) {
        fclose($fp);
        return [
            'success' => false, 
            'error' => "Le service de livraison est momentanément saturé. Veuillez réessayer.", 
            'bordereau' => null
        ];
    }

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
    $host = '10.253.5.101';
    $port = 9000;

    $conn = connectAndLogin($host, $port);
    if (!$conn['fp']) {
        return "Erreur connexion: " . $conn['error'];
    }
    $fp = $conn['fp'];

    // Envoyer STATUS
    fwrite($fp, "STATUS $bordereau\n");
    $response = fgets($fp, 256);
    $status = null;
    
    if (preg_match('/STEP=(\d+)/', $response, $matches)) {
        $status = (int)$matches[1];
    }
    
    fclose($fp);
    return $status;
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
            <div><p>Chez Alizon</p></div>
            <div><p>Chez le transporteur</p></div>
            <div><p>Sur la plateforme régionale</p></div>
            <div><p>Au centre local</p></div>
            <div><p>Livré</p></div>
        </div>

    </body>

    <?php include __DIR__ . '/../../partials/footer.html';?>
</html>
