<?php 
session_start(); 

// Utilisation de require_once avec __DIR__ pour éviter l'erreur "PDO on null"
include '../../../selectBDD.php';
include __DIR__ . '../../../fonctions.php';

// Vérification de connexion
if(empty($_SESSION['vendeur_id'])){
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit();
}
$vendeur_id = $_SESSION['vendeur_id'];

// Nettoyage session et dossiers temporaires
$_SESSION['creerArticle'] = [];
$_SESSION['remise'] = [];
$_SESSION['promotion'] = [];
$fichiers = glob('create/temp_/*');
foreach ($fichiers as $value) {
    if(is_file($value)) unlink($value);
}

try {
    $query = "
    SELECT c.id_produit,
           c.quantite,
           p.p_nom,
           p.p_prix,
           p.p_nb_ventes,
           pc.timestamp_commande AS date
    FROM cobrec1._panier_commande pc ON f.id_panier = pc.id_panier
    LEFT JOIN cobrec1._contient c ON pc.id_panier = c.id_panier
    LEFT JOIN cobrec1._produit p ON c.id_produit = p.id_produit
    WHERE p.id_vendeur = :id_vendeur";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>Alizon - Commandes Vendeur</title>
    <link rel="stylesheet" href="/styles/CommandeVendeur/commande.css" />
</head>
<body>
    <div class="app">
        <?php include __DIR__ . '/../../../partials/aside.html'; ?>
        
        <main class="main">
            <header class="header">
                <h1>Statistique</h1>
            </header>
        </main>
    </div>
</body>
</html>