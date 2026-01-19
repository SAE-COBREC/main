<?php
session_start();
$sth = null ;
$dbh = null ;
include '../../selectBDD.php';
$pdo->exec("SET search_path to cobrec1");

// Recupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;

try {//Récupération des infos de la reduc
    $sql = '
    SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
    WHERE id_panier = :panier;'
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


    $sql = 'SELECT id_client, timestamp_commande FROM cobrec1._panier_commande
    WHERE id_panier = :panier_commande;'
    ;
    $stmt = $pdo->prepare($sql);
    $params = [
        'panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]
    ];
    $stmt->execute($params);
    $_SESSION["post-achat"]["panier"] = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
}catch (Exception $e){
    print_r($e);
}


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
    $host = '127.0.0.1';
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
    $host = '127.0.0.1';
    $port = 9000;
    
    // Timeout de connexion court (1s)
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if (!$fp) {
        return ['success' => false, 'error' => "Serveur injoignable", 'bordereau' => null];
    }

    // Définir un timeout de lecture court (ex: 2 secondes) pour éviter que la page charge indéfiniment
    stream_set_timeout($fp, 2);

    fwrite($fp, "LOGIN Alizon mdp\n");
    fgets($fp, 256); // Ignorer la réponse login pour l'exemple

    // 1. Demander le statut
    fwrite($fp, "STATUS $bordereau\n"); 
    
    // 2. Lire la réponse principale (STEP)
    $response = fgets($fp, 256);
    $result = ['step' => 0, 'detail' => '', 'img_data' => null];

    if ($response && preg_match('/STEP=(\d+)/', $response, $matches)) {
        $result['step'] = (int)$matches[1];
    }

    // 3. Si étape 5, on tente de lire la suite
    if ($result['step'] == 5) {
        // On essaie de lire la ligne de détail
        $detailLine = fgets($fp, 256);
        
        if ($detailLine) {
            // Nettoyage du texte "LIVRE: "
            if (strpos($detailLine, "LIVRE:") !== false) {
                 $result['detail'] = trim(str_replace("LIVRE: ", "", $detailLine));
            } else {
                 $result['detail'] = trim($detailLine);
            }

            // On regarde si une ligne IMG_START suit
            // Note: fgets peut bloquer si le serveur n'envoie rien. 
            // Grâce au stream_set_timeout plus haut, ça ne bloquera que 2s max.
            $imgHeader = fgets($fp, 256);
            
            if ($imgHeader && preg_match('/IMG_START (\d+)/', $imgHeader, $m)) {
                $size = (int)$m[1];
                $imgData = '';
                $bytesRead = 0;

                // Lecture binaire stricte
                while ($bytesRead < $size) {
                    // On lit par paquets (max 8192 ou ce qu'il reste)
                    $chunkSize = min(8192, $size - $bytesRead);
                    $chunk = fread($fp, $chunkSize);
                    
                    if ($chunk === false || strlen($chunk) === 0) {
                        // Arrêt si connexion coupée ou timeout
                        break;
                    }
                    
                    $imgData .= $chunk;
                    $bytesRead += strlen($chunk);
                }

                if ($bytesRead == $size) {
                    $result['img_data'] = base64_encode($imgData);
                }
            }
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
    <?php if (isset($status) && $status['step'] == 5): ?>
    <div class="delivery-info">
        <p>Status actuel : <strong>Livré</strong></p>
        <p>Détail : <?= htmlspecialchars($status['detail']) ?></p>

        <?php if (!empty($status['img_data'])): ?>
        <div class="proof-photo">
            <p>Preuve de livraison :</p>
            <img src="data:image/jpeg;base64,<?= $status['img_data'] ?>" alt="Photo boite aux lettres"
                style="max-width: 300px; border: 2px solid #333;">
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($status['step']): ?>
    <?php if ($resultat['success']): //regarde si il y a pas d'erreur?>
    <p>Numéro de bordereau : <strong><?= htmlspecialchars($resultat['bordereau']) ?></strong></p>
    <p>Status actuel : <strong id="status-value"><?= $status['step'] !== null ? $status['step'] : 'Inconnu' ?></strong>
    </p>
    <?php else: ?>
    <p>Erreur : <?= htmlspecialchars($resultat['error']) ?></p>
    <?php endif; ?>
    <?php endif; ?>


    <div class="steps">
        <img id="steps" src="../../img/svg//Delivrator/<?= $status['step'] !== null ? $status['step'] : 1 ?>steps.svg"
            alt="Box">
    </div>
    <div class="steps2">
        <div>
            <p>Chez Alizon</p>
        </div>
        <div>
            <p>Chez le transporteur</p>
        </div>
        <div>
            <p>Sur la plateforme régionale</p>
        </div>
        <div>
            <p>Au centre local</p>
        </div>
        <div>
            <p>Livré</p>
        </div>
    </div>
    <article class="recapCommande">
        <a href="../post-achat/impression.php" target="_blank" rel="noopener noreferrer"><button>Télécharger la
                facture</button></a>
    </article>

</body>

<?php include __DIR__ . '/../../partials/footer.html';?>

</html>