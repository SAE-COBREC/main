<?php
session_start();
header('Content-Type: application/json');

//vérifier que l'utilisateur est connecté
if (!isset($_SESSION['idClient'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $prenom = htmlspecialchars($_POST['prenom'] ?? 'Client');
    $file = $_FILES['image'];
    
    //vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }
    
    //vérifier que c'est une image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPEG, PNG, GIF ou WebP']);
        exit;
    }
    
    //vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5 MB)']);
        exit;
    }
    
    //récupérer l'extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        //déterminer l'extension depuis le type MIME
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $extensions[$mimeType] ?? 'jpg';
    }
    
    //créer le nom de fichier basé sur i_alt (Photo de profil [prenom])
    $alt = "Photo de profil " . $prenom;
    $filename = $alt . '.' . $extension;
    
    //nettoyer le nom de fichier (enlever les caractères spéciaux)
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    
    //définir le répertoire de destination (chemin absolu depuis la racine du projet)
    $uploadDir = __DIR__ . '/../../img/clients/';
    
    //créer le répertoire s'il n'existe pas
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire de destination']);
            exit;
        }
    }
    
    $uploadPath = $uploadDir . $filename;
    
    //supprimer l'ancienne image si elle existe avec un nom différent
    $pattern = $uploadDir . 'Photo_de_profil_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prenom) . '.*';
    foreach (glob($pattern) as $oldFile) {
        if ($oldFile !== $uploadPath) {
            unlink($oldFile);
        }
    }
    
    //séplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        //retourner le chemin relatif pour la base de données
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
