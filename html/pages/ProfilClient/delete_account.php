<?php
// Démarrer la session pour récupérer les informations du client
session_start();

// Inclure le fichier de configuration pour la connexion à la base de données
require_once __DIR__ . '/../../selectBDD.php';

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../fonctions.php';

// Récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

// Définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// Vérifier si le client est connecté via la session
if (!isset($_SESSION['idClient'])) {
    // Rediriger vers la page de connexion si pas connecté
    $url = '/pages/connexionClient/index.php';
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '"></head></html>';
    exit;
}

// Récupérer l'identifiant du client depuis la session
$identifiantClientConnecte = (int) $_SESSION['idClient'];

// Récupérer l'identifiant du compte associé au client
$identifiantCompteClient = recupererIdentifiantCompteClient($connexionBaseDeDonnees, $identifiantClientConnecte);

// Vérifier que le compte existe bien
if ($identifiantCompteClient === null) {
    // Afficher un message d'erreur si le compte n'existe pas
    die("Compte introuvable pour le client.");
}

// La suppression doit obligatoirement se faire en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $url = '/pages/ProfilClient/index.php?erreur=' . urlencode('Méthode non autorisée pour la suppression du compte.');
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '"></head></html>';
    exit;
}

// Vérifier la présence du mot de passe
$motDePasseSaisi = $_POST['delete_password'] ?? '';
if ($motDePasseSaisi === '') {
    $url = '/pages/ProfilClient/index.php?erreur=' . urlencode('Veuillez saisir votre mot de passe pour confirmer la suppression.');
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '"></head></html>';
    exit;
}

// Vérifier le mot de passe actuel du compte
$requeteMotDePasse = "SELECT mdp FROM cobrec1._compte WHERE id_compte = ?";
$requetePrepareeMotDePasse = $connexionBaseDeDonnees->prepare($requeteMotDePasse);
$requetePrepareeMotDePasse->execute([$identifiantCompteClient]);
$motDePasseHash = $requetePrepareeMotDePasse->fetchColumn();

if ($motDePasseHash === false || !password_verify($motDePasseSaisi, $motDePasseHash)) {
    $url = '/pages/ProfilClient/index.php?erreur=' . urlencode('Mot de passe incorrect. La suppression du compte a été annulée.');
    echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '"></head></html>';
    exit;
}

// Appeler la fonction de suppression du compte
$resultatSuppression = supprimerCompteClient($connexionBaseDeDonnees, $identifiantClientConnecte, $identifiantCompteClient);

// Supprimer le cookie alizon_owner pour empêcher l'édition des avis anonymisés
if (isset($_COOKIE['alizon_owner'])) {
    setcookie('alizon_owner', '', time() - 3600, '/');
    unset($_COOKIE['alizon_owner']);
}

// Détruire la session
session_unset();
session_destroy();

// Rediriger vers la page d'accueil avec un message dans l'URL
if ($resultatSuppression['success']) {
    $url = '/index.php?compte_supprime=true';
} else {
    $url = '/pages/ProfilClient/index.php?erreur=' . urlencode($resultatSuppression['message']);
}

echo '<!doctype html><html lang="fr"><head><meta http-equiv="refresh" content="0;url=' . $url . '"></head></html>';
exit;
?>
