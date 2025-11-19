<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("=== DEBUT UPLOAD ===");
error_log("Upload error code: " . $_FILES['image']['error']);
error_log("Destination: " . __DIR__ . '/../../img/clients/');

//démarrer la session pour vérifier l'authentification
session_start();

//vérifier si le client est connecté
if (!isset($_SESSION['idClient'])) {
    //retourner une erreur JSON si non autorisé
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

//traiter uniquement les requêtes POST avec un fichier image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    //récupérer l'identifiant du client depuis les données POST
    $identifiantClient = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    
    //récupérer les informations du fichier uploadé
    $fichierImage = $_FILES['image'];
    
    //vérifier que l'identifiant client est valide
    if ($identifiantClient <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID client invalide']);
        exit;
    }
    
    //vérifier qu'il n'y a pas d'erreur lors de l'upload
    if ($fichierImage['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }
    
    //définir les types MIME autorisés pour les images
    $typesImagesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    //détecter le type MIME réel du fichier uploadé
    $descripteurInformationFichier = finfo_open(FILEINFO_MIME_TYPE);
    $typeMimeFichier = finfo_file($descripteurInformationFichier, $fichierImage['tmp_name']);
    finfo_close($descripteurInformationFichier);
    
    //vérifier que le type MIME est autorisé
    if (!in_array($typeMimeFichier, $typesImagesAutorises)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Utilisez JPEG, PNG, GIF ou WebP']);
        exit;
    }
    
    //vérifier que la taille du fichier ne dépasse pas 5 MB
    if ($fichierImage['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5 MB)']);
        exit;
    }
    
    //extraire l'extension du fichier depuis son nom
    $extensionFichier = pathinfo($fichierImage['name'], PATHINFO_EXTENSION);
    
    //si l'extension est vide, la déduire du type MIME
    if (empty($extensionFichier)) {
        //correspondance entre type MIME et extension
        $correspondancesExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extensionFichier = $correspondancesExtensions[$typeMimeFichier] ?? 'jpg';
    }
    
    //construire le nom final du fichier avec l'identifiant du client
    $nomFichierFinal = "Photo_de_profil_id_" . $identifiantClient . "." . $extensionFichier;
    
    //définir le répertoire de destination pour les images clients
    $repertoireDestinationUpload = __DIR__ . '/../../img/clients/';
    
    //créer le répertoire s'il n'existe pas
    if (!file_exists($repertoireDestinationUpload)) {
        if (!mkdir($repertoireDestinationUpload, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire de destination']);
            exit;
        }
    }
    
    //construire le chemin complet du fichier de destination
    $cheminCompletDestination = $repertoireDestinationUpload . $nomFichierFinal;
    
    //supprimer les anciennes photos de profil du client (toutes extensions confondues)
    $patternFichiersExistants = $repertoireDestinationUpload . 'Photo_de_profil_id_' . $identifiantClient . '.*';
    foreach (glob($patternFichiersExistants) as $ancienFichier) {
        //si l'ancien fichier existe déjà, le supprimer
        if ($ancienFichier !== $cheminCompletDestination && is_file($ancienFichier)) {
            unlink($ancienFichier);
        }
    }
    
    //déplacer le fichier uploadé vers le répertoire de destination
    if (move_uploaded_file($fichierImage['tmp_name'], $cheminCompletDestination)) {
        //construire le chemin relatif depuis la racine du site
        $cheminRelatifDepuisRacine = '/img/clients/' . $nomFichierFinal;
        
        //retourner une réponse JSON de succès avec le chemin du fichier
        echo json_encode([
            'success' => true,
            'path' => $cheminRelatifDepuisRacine,
            'filename' => $nomFichierFinal,
            'message' => 'Image uploadée avec succès'
        ]);
    } else {
        //retourner une erreur si la sauvegarde a échoué
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
    }
} else {
    //retourner une erreur si aucun fichier n'a été reçu
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
}
?>