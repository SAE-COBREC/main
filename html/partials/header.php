<?php
session_start();
$id_compte = $_SESSION['idCompte'];
echo $id_compte;
    $pdo->exec("SET search_path TO cobrec1");

    $requeteImageProfile = "SELECT _image.id_image, _image.i_lien, _image.i_title, _image.i_alt
                            FROM cobrec1._image 
                            INNER JOIN cobrec1._represente_compte ON _image.id_image = _represente_compte.id_image
                            WHERE _represente_compte.id_compte = :id_compte";

    $stmt = $pdo->prepare($requeteImageProfile);
    $stmt->execute([
        ':id_compte' => $id_compte
    ]); 
    $lienImage = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<header class="site-header" role="banner">
    <div class="header-inner">
        <div class="logo-container">
            <a href="/" class="brand">
                <img src="/img/svg/logo-text.svg" alt="Alizon" class="logo" />
            </a>
        </div>

        <div class="search-container">
            <img src="/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
            <input type="text" placeholder="Rechercher des produits..." class="search-input">
        </div>

        <div class="icons-container">
            <a href="/pages/panier/index.php" class="icon-link">
                <img src="/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                    style="filter: invert(1) saturate(0.9);">
            </a>
            <?php if($id_compte): ?>
                <a href="/pages/ProfilClient/index.php" class="icon-link">
                    <img src="<?php echo $lienImage?>" alt="Profile" class="fas fa-shopping-cart icon">
                </a> 
            <?php else: ?>
                <a href="/pages/ProfilClient/index.php" class="icon-link">
                    <img src="/img/svg/profile.svg" alt="Profile" class="fas fa-shopping-cart icon">
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="separator"></div>

<?php session_unset();
    session_destroy(); ?>