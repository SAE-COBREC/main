<?php
//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
include '../../selectBDD.php';

$sth = null;
$dbh = null;

//crée la connexion à la base de données
$pdo->exec("SET search_path TO cobrec1");

// Récupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;
if (!str_contains($_SERVER['HTTP_REFERER'], 'ProfilClient')){
    try {
        //Récupération des infos de la facture
        $sql = '
        SELECT id_facture, id_panier, id_adresse, nom_destinataire, prenom_destinataire, f_total_ht, f_total_remise, f_total_ttc FROM cobrec1._facture
        WHERE id_panier = :panier;';
        $stmt = $pdo->prepare($sql);
        $params = ['panier' => $id_commande];
        $stmt->execute($params);
        $_SESSION["post-achat"]["facture"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION["post-achat"]["facture"] = $_SESSION["post-achat"]["facture"][0];

        $sql = '
        SELECT id_panier, id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA FROM cobrec1._contient
        WHERE id_panier = :panier_commande;';
        $stmt = $pdo->prepare($sql);
        $params = ['panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]];
        $stmt->execute($params);
        $_SESSION["post-achat"]["contient"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = 'SELECT id_client, timestamp_commande FROM cobrec1._panier_commande
        WHERE id_panier = :panier_commande;';
        $stmt = $pdo->prepare($sql);
        $params = ['panier_commande' => $_SESSION["post-achat"]["facture"]["id_panier"]];
        $stmt->execute($params);
        $_SESSION["post-achat"]["panier"] = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
    } catch (Exception $e) {
        print_r($e);
    }
}

// Ouvre une connexion socket et s'authentifie automatiquement
function connectAndLogin($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$fp) {
        return ['fp' => false, 'error' => "Transporteur non disponible: $errstr ($errno)"];
    }

    fwrite($fp, "LOGIN Alizon Alizon1!\n");
    $loginResponse = fgets($fp, 256);

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

    $createCmd = "CREATE_BORDEREAU $id_commande\n";
    fwrite($fp, $createCmd);

    stream_set_timeout($fp, 2);
    $response = fgets($fp, 256);

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

    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if (!$fp) {
        return ['success' => false, 'error' => "Serveur injoignable", 'bordereau' => null];
    }

    stream_set_timeout($fp, 2);

    fwrite($fp, "LOGIN Alizon Alizon1!\n");
    fgets($fp, 256);

    fwrite($fp, "STATUS $bordereau\n");

    $response = fgets($fp, 256);
    $result = ['step' => 0, 'detail' => '', 'img_data' => null];

    if ($response && preg_match('/STEP=(\d+)/', $response, $matches)) {
        $result['step'] = (int)$matches[1];
    }

    if ($result['step'] == 5) {
        $detailLine = fgets($fp, 256);

        if ($detailLine) {
            if (strpos($detailLine, "LIVRE:") !== false) {
                $result['detail'] = trim(str_replace("LIVRE: ", "", $detailLine));
            } else {
                $result['detail'] = trim($detailLine);
            }

            $imgHeader = fgets($fp, 256);

            if ($imgHeader && preg_match('/IMG_START (\d+)/', $imgHeader, $m)) {
                $size = (int)$m[1];
                $imgData = '';
                $bytesRead = 0;

                while ($bytesRead < $size) {
                    $chunkSize = min(8192, $size - $bytesRead);
                    $chunk = fread($fp, $chunkSize);

                    if ($chunk === false || strlen($chunk) === 0) {
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

function getStepLabel($step) {
    $labels = [
        1 => 'Chez Alizon',
        2 => 'Chez le transporteur',
        3 => 'Sur la plateforme régionale',
        4 => 'Au centre local',
        5 => 'Livré'
    ];
    return $labels[$step] ?? 'Inconnu';
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
    <title>Suivi de commande #<?= htmlspecialchars($id_commande) ?> - Alizon</title>
    <link rel="icon" type="image/png" href="../../img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    <link rel="stylesheet" href="/styles/SuiviCommande/style.css">
</head>

<body>
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <main>
        <h1>Suivi de commande #<?= htmlspecialchars($id_commande)?></h1>

        <?php if (!$resultat || !$resultat['success']): ?>
        <div class="error-message">
            <?= htmlspecialchars($resultat['error'] ?? 'Une erreur est survenue') ?>
        </div>
        <?php else: ?>

        <!-- Section de suivi -->
        <section class="tracking-section">
            <div class="tracking-header">
                <h2>État de la livraison</h2>
                <div class="bordereau-badge">
                    Bordereau N° <?= htmlspecialchars($resultat['bordereau']) ?>
                </div>
            </div>

            <?php if (isset($status) && $status['step'] == 5): ?>
            <!-- Livraison terminée -->
            <div class="delivery-complete">
                <h3>Commande livrée</h3>
                <?php if (!empty($status['detail'])): ?>
                <p class="delivery-detail"><?= htmlspecialchars($status['detail']) ?></p>
                <?php endif; ?>

                <?php if (!empty($status['img_data'])): ?>
                <div class="proof-photo">
                    <p>Preuve de livraison</p>
                    <img src="data:image/jpeg;base64,<?= $status['img_data'] ?>" alt="Photo de livraison" tile="Photo de livraison">
                </div>

                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Barre de progression -->
            <div class="progress-container">
                <div class="progress-steps">
                    <div class="progress-line">
                        <div class="progress-line-fill" style="width: <?= ($status['step'] - 1) * 25 ?>%;"></div>
                    </div>

                    <?php 
                        $steps = [
                            1 => 'Chez Alizon',
                            2 => 'Chez le transporteur',
                            3 => 'Plateforme régionale',
                            4 => 'Centre local',
                            5 => 'Livré'
                        ];

                        foreach ($steps as $num => $label): 
                            $stepClass = '';
                            if ($num < $status['step']) {
                                $stepClass = 'completed';
                            } elseif ($num == $status['step']) {
                                $stepClass = 'active';
                            }
                        ?>
                    <div class="step <?= $stepClass ?>">
                        <div class="step-circle">
                            <?php if ($num < $status['step']): ?>
                            <!-- Checkmark sera ajouté via CSS -->
                            <?php else: ?>
                            <?= $num ?>
                            <?php endif; ?>
                        </div>
                        <div class="step-label"><?= htmlspecialchars($label) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Section facture -->
        <?php if (!str_contains($_SERVER['HTTP_REFERER'], 'ProfilClient')){ ?>
        <section class="invoice-section">
            <a href="../post-achat/impression.php" target="_blank" rel="noopener noreferrer" class="invoice-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Télécharger la facture
            </a>
        </section>
        <?php } ?>

        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../partials/footer.html'; ?>
</body>

</html>