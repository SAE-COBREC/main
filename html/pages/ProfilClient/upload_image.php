<?php
session_start();
header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['idClient'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $idClient = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    $file = $_FILES['image'];
    
    // Vérifier que l'ID client est valide
    if ($idClient <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID client invalide']);
        exit;
    }
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }
    
    // Vérifier que c'est une image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPEG, PNG, GIF ou WebP']);
        exit;
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5 MB)']);
        exit;
    }
    
    // Récupérer l'extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        // Déterminer l'extension depuis le type MIME
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $extensions[$mimeType] ?? 'jpg';
    }
    
    // Créer le nom de fichier basé sur l'ID client
    // Format: Photo_de_profil_[id_client].extension
    $filename = "Photo_de_profil_" . $idClient . "." . $extension;
    
    // Définir le répertoire de destination (chemin absolu depuis la racine du projet)
    // On remonte de 2 niveaux depuis /pages/ProfilClient/
    $uploadDir = __DIR__ . '/../../img/clients/';
    
    // Créer le répertoire s'il n'existe pas
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire de destination']);
            exit;
        }
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // Supprimer l'ancienne image si elle existe avec un nom différent (autre extension)
    $pattern = $uploadDir . 'Photo_de_profil_' . $idClient . '.*';
    foreach (glob($pattern) as $oldFile) {
        if ($oldFile !== $uploadPath && is_file($oldFile)) {
            unlink($oldFile);
        }
    }
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // IMPORTANT : Retourner le chemin depuis la RACINE WEB avec un slash au début
        $relativePath = '/img/clients/' . $filename;
        echo json_encode([
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename,
            'message' => 'Image uploadée avec succès'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
}
?>
