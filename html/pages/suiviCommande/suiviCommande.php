<?php
session_start();

// Récupérer le numéro de commande (depuis GET, POST ou session)
$id_commande = $_GET['id_commande'] ?? $_POST['id_commande'] ?? $_SESSION['id_commande'] ?? 0;

// Envoyer le numéro de commande au programme C via socket
function envoyerCommande($id_commande) {
    $host = '127.0.0.1';
    $port = 9000;
    
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        return ['success' => false, 'error' => 'Erreur création socket'];
    }
    
    if (@socket_connect($socket, $host, $port) === false) {
        socket_close($socket);
        return ['success' => false, 'error' => 'Transporteur non disponible'];
    }
    
    // Envoyer le numéro de commande
    socket_write($socket, (string)$id_commande, strlen((string)$id_commande));
    
    // Lire la réponse
    $response = socket_read($socket, 256);
    socket_close($socket);
    
    return ['success' => true, 'response' => $response];
}

// Envoyer si on a un numéro de commande valide
$resultat = null;
if ($id_commande > 0) {
    $resultat = envoyerCommande($id_commande);
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
                <p>Signal envoyé au transporteur : <?= htmlspecialchars($resultat['response']) ?></p>
            <?php else: ?>
                <p>Erreur : <?= htmlspecialchars($resultat['error']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </body>
    <?php include __DIR__ . '/../../partials/footer.html';?>
</html>
