<?php
session_start();

// Récupérer le numéro de commande (depuis GET, POST ou session)
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;

// Récupérer le login et mot de passe depuis la session
$login = $_SESSION['pseudo'] ?? $_SESSION['login'] ?? 'anonymous';
$mdp = $_SESSION['mdp'] ?? 'nopass';

// Envoyer le numéro de commande au programme C via socket
// Protocole: LOGIN user pass -> OK LOGGED_IN -> CREATE_LABEL id -> OK LABEL=X ...
function envoyerCommande($id_commande, $login, $mdp) {
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
    
    // 1) Envoyer LOGIN
    $loginCmd = "LOGIN $login $mdp\n";
    socket_write($socket, $loginCmd, strlen($loginCmd));
    
    // Lire la réponse LOGIN
    $response = socket_read($socket, 256);
    if (strpos($response, 'OK LOGGED_IN') === false) {
        socket_close($socket);
        return ['success' => false, 'error' => 'Erreur login transporteur', 'bordereau' => null];
    }
    
    // 2) Envoyer CREATE_LABEL
    $createCmd = "CREATE_LABEL $id_commande\n";
    socket_write($socket, $createCmd, strlen($createCmd));
    
    // Lire la réponse avec le bordereau
    $response = socket_read($socket, 256);
    socket_close($socket);
    
    // Parser la réponse: OK LABEL=X ALREADY_EXISTS=0 STEP=1 LABEL_STEP="..."
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

// Envoyer si on a un numéro de commande valide
$resultat = null;
if ($id_commande > 0) {
    $resultat = envoyerCommande($id_commande, $login, $mdp);
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
            <?php else: ?>
                <p>Erreur : <?= htmlspecialchars($resultat['error']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
                
        <div class="barre">
            <div>
            <p>Entrepot Alizon</p>
            </div>
            <div>
            <p>plateforme du transporteur</p>
            </div>
            <div>
            <p>plateforme régionale</p>
            </div>
            <div>
            <p>plateforme locale</p>
            </div>
            <div>
            <p>Le colis est livré</p>
            </div>
        </div>
    </body>
    <?php include __DIR__ . '/../../partials/footer.html';?>
</html>