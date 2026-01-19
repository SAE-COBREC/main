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
    // Requête optimisée pour récupérer les détails des produits
    $query = "
    SELECT f.id_facture,
           f.id_panier,
           c.id_produit,
           c.quantite,
           p.p_nom,
           p.p_prix,
           pc.timestamp_commande AS date
    FROM _facture f
    LEFT JOIN _panier_commande pc ON f.id_panier = pc.id_panier
    LEFT JOIN _contient c ON pc.id_panier = c.id_panier
    LEFT JOIN _produit p ON c.id_produit = p.id_produit
    WHERE p.id_vendeur = :id_vendeur
    ORDER BY f.id_facture DESC"; // Les plus récentes en premier

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
                <h1>Commande Vendeur</h1>
            </header>

            <section class="liste-commandes">
                <?php if (empty($commandes)): ?>
                    <div class="empty-state">Aucune commande n'a été passée pour vos articles.</div>
                <?php else: ?>
                    <?php 
                    $dernier_id_facture = null; 
                    foreach ($commandes as $commande): 
                        // Nouveau bloc si l'ID de facture change
                        if ($commande['id_facture'] !== $dernier_id_facture): 
                            if ($dernier_id_facture !== null) echo "</div></div>"; 
                            $dernier_id_facture = $commande['id_facture'];
                    ?>
                        <div class="facture-card">
                            <div class="facture-header">
                                <h3>Facture #<?php echo htmlspecialchars($commande['id_facture']); ?></h3>
                                <p><?php echo date("j/m/Y H:m", strtotime($commande['date'])); ?></p>
                                <a href="../../post-achat/profil.php?id=<?php echo $commandeIndividuelle['id_panier']; ?>"
                                    target="_blank" rel="noopener noreferrer">
                                    <button type="button" class="btn-download">
                                        Télécharger la facture
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </button>
                                </a>
                            </div>
                            <div class="facture-content">
                                <div class="produits-title">Produits vendus :</div>
                    <?php endif; ?>
                        
                        <div class="produit-item">
                            <span class="prod-id">Ref: <?php echo htmlspecialchars($commande['id_produit']); ?></span>
                            <span class="prod-name"><?php echo htmlspecialchars($commande['p_nom'] ?? 'Produit inconnu');?> x<?php echo htmlspecialchars($commande['quantite']);?></span>
                        </div>
                    <?php endforeach; ?>
                    </div></div> <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>