<?php
session_start();

if (isset($_POST['theme'])) {
    // Nettoyage de la valeur pour la sécurité
    $theme = htmlspecialchars($_POST['theme']);
    $_SESSION['colorblind_mode'] = $theme;
    echo json_encode(['status' => 'success', 'theme' => $theme]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No theme provided']);
}
?>