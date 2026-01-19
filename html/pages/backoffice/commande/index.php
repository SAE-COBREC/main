<?php 
// Démarrage de la session PHP pour accéder aux variables de session
session_start(); 
?>

<?php
// Inclusion de fichier 
include '../../../selectBDD.php';
include __DIR__ . '../../../fonctions.php';

// Récupération de l'ID du vendeur connecté depuis la session
if(empty($_SESSION['vendeur_id']) === false){
  $vendeur_id = $_SESSION['vendeur_id'];
}else{
?>
<script>
    alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
    document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
}

// Initialisation d'un tableau vide pour stocker éventuellement de nouveaux articles
$_SESSION['creerArticle'] = [];
$_SESSION['remise'] = [];
$_SESSION['promotion'] = [];
$fichiers = glob('create/temp_/*');
foreach ($fichiers as $value) {
  unlink($value);
}

try {
    $query = "
    SELECT id_facture,
        _facture.id_panier,
        _contient.id_produit,
        _produit.id_vendeur
        FROM _facture
        LEFT JOIN _panier_commande ON _facture.id_panier = _panier_commande.id_panier
        LEFT JOIN _contient ON _panier_commande.id_panier = _contient.id_panier
        LEFT JOIN _produit ON _contient.id_produit = _produit.id_produit
        WHERE _produit.id_vendeur = :id_vendeur
        ORDER BY id_facture";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1440, height=1024" />
  <title>Alizon - Page Accueil Vendeur</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="/styles/CommandeVendeur/commande.scss" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../../../partials/aside.html'; ?>
    
    <main class="main">
      <div class="header">
        <h1 class="header__title">Page accueil vendeur</h1>
    </main>
  </div>
</body>
</html>