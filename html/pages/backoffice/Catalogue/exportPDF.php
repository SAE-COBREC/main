<?php
session_start();
include '../../../selectBDD.php';
include __DIR__ . '/../../../pages/fonctions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Vérification de la connexion du vendeur
if (empty($_SESSION['vendeur_id'])) {
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit;
}

$vendeur_id = $_SESSION['vendeur_id'];

// Connexion BDD
$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// Récupération infos vendeur
function getVendeurInfoPDF($pdo, $vendeur_id) {
    $stmt = $pdo->prepare("SELECT denomination FROM cobrec1._vendeur WHERE id_vendeur = :id");
    $stmt->execute(['id' => $vendeur_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$vendeurInfos = getVendeurInfoPDF($pdo, $vendeur_id);
$nom_entreprise = $vendeurInfos['denomination'] ?? 'Entreprise inconnue';

// Récupération des produits
$listeIdProduit = $_POST['produits_selectionnes'] ?? [];
$listeProduits = [];

if (!empty($listeIdProduit)) {
    foreach ($listeIdProduit as $idProduit) {
        $produit = getProduitParId($connexionBaseDeDonnees, (int)$idProduit, (int)$vendeur_id);
        if (!empty($produit)) {
            $listeProduits[] = $produit;
        }
    }
} else {
    // Si aucun produit sélectionné, prendre tous les produits en ligne
    $listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $nom_entreprise);
}

// Chemin de base pour les images
$basePath = realpath(__DIR__ . '/../../../');

// Construction du HTML via le template (produit la variable $html)
include __DIR__ . '/catalogue_template.php';

// Conversion HTML → PDF avec DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', $basePath);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Catalogue_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nom_entreprise) . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
