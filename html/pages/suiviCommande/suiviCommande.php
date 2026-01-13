<?php
session_start();


$SIGNAL_FILE = '/tmp/php_signal.flag';
$action_effectuee = false;

if (file_exists($SIGNAL_FILE)) {
    $timestamp = (int)file_get_contents($SIGNAL_FILE);
    $dernier_check = isset($_SESSION['last_signal_check']) ? $_SESSION['last_signal_check'] : 0;
    
    if ($timestamp > $dernier_check) {
        $_SESSION['last_signal_check'] = $timestamp;
        $action_effectuee = true;
    }
}


// Récupérer le num de commande
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;

function envoyerCommande($id_commande) {
    $host = '127.0.0.1';
    $port = 9000;
    
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        return ['success' => false, 'error' => 'Erreur création socket', 'bordereau' => null];
    }
    
    if (@socket_connect($socket, $host, $port) === false) {
        socket_close($socket);
        return ['success' => false, 'error' => 'Transporteur non disponible', 'bordereau' => null];
    }
    
    // Envoyer CREATE_LABEL
    $createCmd = "CREATE_LABEL $id_commande\n";
    socket_write($socket, $createCmd, strlen($createCmd));
    
    // Lire la réponse
    $response = socket_read($socket, 256);
    socket_close($socket);
    
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

// Recuperer le status
function getStatus($bordereau) {
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) return null;
    
    if (@socket_connect($socket, '127.0.0.1', 9000) === false) {
        socket_close($socket);
        return null;
    }
    
    socket_write($socket, "STATUS $bordereau\n");
    $response = socket_read($socket, 256);
    socket_close($socket);
    
    if (preg_match('/STEP=(\d+)/', $response, $m)) {
        return (int)$m[1];
    }
    return null;
}

// Envoyer la commmande
$resultat = null;
$status = null;
if ($id_commande > 0) {
    $resultat = envoyerCommande($id_commande);
    if ($resultat && $resultat['success'] && $resultat['bordereau']) {
        $status = getStatus($resultat['bordereau']);
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
   
    <?php if ($resultat && $resultat['success']): ?>
    <script>
        const bordereau = <?= $resultat['bordereau'] ?>;
        let lastCheck = Math.floor(Date.now() / 1000);
        let lastStatus = document.getElementById('status-value').textContent;
        
        function checkForSignal() {
            fetch('checkSignal.php?bordereau=' + bordereau + '&lastCheck=' + lastCheck)
                .then(response => response.json())
                .then(data => {
                    if (data.signal && data.status !== null) {
                        // Signal reçu ! Mettre à jour le status
                        lastCheck = data.timestamp;
                        if (data.status != lastStatus) {
                            lastStatus = data.status;
                            document.getElementById('status-value').textContent = data.status;
                            // Mettre à jour l'image
                            document.getElementById('steps').src = '../../img/svg//Delivrator/' + data.status + 'steps.svg';
                            console.log('🔔 Signal reçu ! Nouveau status:', data.status);
                        }
                    }
                })
                .catch(err => console.error('Erreur:', err));
        }
        
        // Vérifier les signaux toutes les 2 secondes
        setInterval(checkForSignal, 2000);
        
        // Premier check immédiat
        checkForSignal();
    </script>
    <?php endif; ?>
    
    <?php include __DIR__ . '/../../partials/footer.html';?>
</html>
<script>

</script>