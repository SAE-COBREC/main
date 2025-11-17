<?php
session_start();

if (!isset($_SESSION['idClient'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $identifiantClient = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    $fichierImage = $_FILES['image'];
    
    if ($identifiantClient <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID client invalide']);
        exit;
    }
    
    if ($fichierImage['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }
    
    $typesImagesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $descripteurInformationFichier = finfo_open(FILEINFO_MIME_TYPE);
    $typeMimeFichier = finfo_file($descripteurInformationFichier, $fichierImage['tmp_name']);
    finfo_close($descripteurInformationFichier);
    
    if (!in_array($typeMimeFichier, $typesImagesAutorises)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPEG, PNG, GIF ou WebP']);
        exit;
    }
    
    if ($fichierImage['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5 MB)']);
        exit;
    }
    
    $extensionFichier = pathinfo($fichierImage['name'], PATHINFO_EXTENSION);
    if (empty($extensionFichier)) {
        $correspondancesExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extensionFichier = $correspondancesExtensions[$typeMimeFichier] ?? 'jpg';
    }
    
    $nomFichierFinal = "Photo_de_profil_id_" . $identifiantClient . "." . $extensionFichier;
    
    $repertoireDestinationUpload = __DIR__ . '/../../img/clients/';
    
    if (!file_exists($repertoireDestinationUpload)) {
        if (!mkdir($repertoireDestinationUpload, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire de destination']);
            exit;
        }
    }
    
    $cheminCompletDestination = $repertoireDestinationUpload . $nomFichierFinal;
    
    $patternFichiersExistants = $repertoireDestinationUpload . 'Photo_de_profil_' . $identifiantClient . '.*';
    foreach (glob($patternFichiersExistants) as $ancienFichier) {
        if ($ancienFichier !== $cheminCompletDestination && is_file($ancienFichier)) {
            unlink($ancienFichier);
        }
    }
    
    if (move_uploaded_file($fichierImage['tmp_name'], $cheminCompletDestination)) {
        $cheminRelatifDepuisRacine = '/../../img/clients/' . $nomFichierFinal;
        echo json_encode([
            'success' => true,
            'path' => $cheminRelatifDepuisRacine,
            'filename' => $nomFichierFinal,
            'message' => 'Image uploadée avec succès'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
}
?>