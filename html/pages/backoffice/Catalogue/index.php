<?php
session_start();
include '../../../selectBDD.php';

// Vérification de la connexion du vendeur
if (empty($_SESSION['vendeur_id'])) {
?>
<script>
alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
    exit;
}

$vendeur_id = $_SESSION['vendeur_id'];

// Récupération du nom de l'entreprise et de la photo de profil du vendeur connecté
$stmt = $pdo->prepare('
    SELECT v.denomination, v.raison_sociale, c.email, c.num_telephone, i.i_lien AS image
    FROM cobrec1._vendeur v
    LEFT JOIN cobrec1._compte c ON v.id_compte = c.id_compte
    LEFT JOIN cobrec1._represente_compte rc ON c.id_compte = rc.id_compte
    LEFT JOIN cobrec1._image i ON rc.id_image = i.id_image
    WHERE v.id_vendeur = :id_vendeur
    LIMIT 1
');
$stmt->execute(['id_vendeur' => $vendeur_id]);
$vendeur = $stmt->fetch(PDO::FETCH_ASSOC);
$nom_entreprise = $vendeur ? htmlspecialchars($vendeur['denomination']) : 'Entreprise inconnue';
$raison_sociale = $vendeur && !empty($vendeur['raison_sociale']) ? htmlspecialchars($vendeur['raison_sociale']) : '';
$email = $vendeur && !empty($vendeur['email']) ? htmlspecialchars($vendeur['email']) : '';
$telephone = $vendeur && !empty($vendeur['num_telephone']) ? htmlspecialchars($vendeur['num_telephone']) : '';
$photo_profil = !empty($vendeur['image']) ? htmlspecialchars($vendeur['image']) : '/img/svg/market.svg';
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport">
    <title>Catalogue Vendeur - Alizon</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
    <link rel="stylesheet" href="../../..//styles/CatalogueVendeur/Catalogue.css">
</head>

<body>
    <header></header>
    <main>
        <div class="container">
            <h1>Catalogue<p></p> Produit</h1>
            <img src="<?php echo $photo_profil; ?>" alt="<?php echo $nom_entreprise; ?>" class="logo-catalogue">
            <p>Par <?php echo $nom_entreprise; ?></p>
        </div>
        </div>
    </main>
    <footer>
        <p>© <?php echo $raison_sociale; ?> - <?php echo $email; ?> - <?php echo $telephone; ?></p>
    </footer>
</body>