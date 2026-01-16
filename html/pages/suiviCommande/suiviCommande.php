<?php
session_start();

// Récupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;



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
    $host = '127.0.0.1';
    $port = 9000;
    $fp = fsockopen($host, $port, $errno, $errstr, 2);
    
    if (!$fp) {
        return ['status' => "Erreur connexion", 'img' => null, 'detail' => null];
    }

    // 1. Demander le statut
    fwrite($fp, "STATUS $bordereau\n"); 
    
    // 2. Lire la réponse principale
    $response = fgets($fp, 256);
    $result = ['step' => 0, 'detail' => '', 'img_data' => null];

    // Vérifier l'étape (STEP)
    if (preg_match('/STEP=(\d+)/', $response, $matches)) {
        $result['step'] = (int)$matches[1];
    }

    // Si l'étape est 5 (Livré), on regarde s'il y a des détails supplémentaires
    if ($result['step'] == 5) {
        // Lire la ligne suivante qui contient les détails (envoyée par le C)
        $detailLine = fgets($fp, 256); 
        
        // Vérifier si une image arrive
        if (strpos($detailLine, "IMG_START") !== false) {
             // Extraire la taille de l'image
             if (preg_match('/IMG_START (\d+)/', $detailLine, $m)) {
                 $size = (int)$m[1];
                 $imgData = '';
                 
                 // Lire les octets binaires exacts de l'image
                 while (strlen($imgData) < $size) {
                     $chunk = fread($fp, $size - strlen($imgData));
                     if ($chunk === false) break;
                     $imgData .= $chunk;
                 }
                 // Encoder en base64 pour l'affichage HTML
                 $result['img_data'] = base64_encode($imgData);
                 $result['detail'] = "Livré en boîte aux lettres";
             }
        } else {
             // C'est juste du texte (Livré en main propre ou Refusé)
             // On enlève le préfixe "LIVRE: " pour l'affichage
             $result['detail'] = str_replace("LIVRE: ", "", $detailLine);
        }
    }

    fclose($fp);
    return $result;
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
        <?php if (isset($status) && is_array($status) && $status['step'] == 5): ?>
            <div class="delivery-info">
                <h3>Statut : Livré</h3>
                <p>Détail : <?= htmlspecialchars($status['detail']) ?></p>
                
                <?php if (!empty($status['img_data'])): ?>
                    <div class="proof-photo">
                        <h4>Preuve de livraison :</h4>
                        <img src="data:image/jpeg;base64,<?= $status['img_data'] ?>" 
                            alt="Photo boite aux lettres" 
                            style="max-width: 300px; border: 2px solid #333;">
                    </div>
                <?php endif; ?>
            </div>
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