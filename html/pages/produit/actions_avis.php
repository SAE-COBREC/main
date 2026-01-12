<?php
// Endpoint AJAX pour les actions sur les avis.
// Utilise un tampon de sortie pour éviter que du HTML ou des warnings casse le JSON renvoyé.
ob_start();
session_start();
try {
    require_once __DIR__ . '/../../selectBDD.php';
    require_once __DIR__ . '/../../pages/fonctions.php';
} catch (Throwable $t) {
    $out = ob_get_clean();
    error_log('actions_avis include error: ' . $t->getMessage() . '\nOutput:' . $out);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur (include)']);
    exit;
}

$pdo->exec("SET search_path TO cobrec1");

$idClient = isset($_SESSION['idClient']) ? (int)$_SESSION['idClient'] : null;
$idProduit = isset($_POST['id_produit']) ? (int)$_POST['id_produit'] : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    // Appel du handler central qui renvoie lui-même du JSON
    gererActionsAvis($pdo, $idClient, $idProduit);
} catch (Exception $e) {
    $out = ob_get_clean();
    error_log('actions_avis exception: ' . $e->getMessage() . '\nOutput:' . $out);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage(), '_debug_output' => substr($out,0,1000)]);
    exit;
}

// If gererActionsAvis returns normally (it usually echoes and exits), flush any buffer.
$out = ob_get_clean();
if ($out !== '') {
    // Log stray output then return generic success if no JSON was sent
    error_log('actions_avis stray output: ' . substr($out,0,1000));
}
exit;
