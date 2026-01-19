<?php 
// Démarrage de la session PHP pour accéder aux variables de session
session_start(); 
?>

<?php
// Inclusion de fichier 
include '../../selectBDD.php';

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
        _facture.id_article,
        _contient.id_produit,
        _produit.id_vendeur
        FROM _fature
        LEFT JOIN _panier ON _facture.id_panier = _panier.id_panier
        LEFT JOIN _contient ON _panier.id_panier = _contient.id_panier
        LEFT JOIN _produit ON _contient.id_produit = _produit.id_produit
        WHERE _produit.id_vendeur = :id_vendeur
        ORDER BY id_facture";

    $stmt = $pdo->prepare($query);
    $stmt->execute($vendeur_id);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}