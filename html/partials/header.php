<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if we are in an include or standalone (AJAX)
if (!isset($pdo) && !isset($connexionBaseDeDonnees)) {
    require_once __DIR__ . '/../selectBDD.php';
    $pdo->exec("SET search_path TO cobrec1");
}

$dbForHeader = isset($connexionBaseDeDonnees) ? $connexionBaseDeDonnees : (isset($pdo) ? $pdo : null);

if (!function_exists('recupererIdentifiantCompteClient')) {
    require_once __DIR__ . '/../pages/fonctions.php';
}

$headerProfileImage = "/img/svg/profile.svg";
$headerProfileClass = "fas fa-shopping-cart icon";
$headerProfileStyle = "";

if (isset($_SESSION['idClient'])) {
    if ($dbForHeader) {
         $headerIdCompte = recupererIdentifiantCompteClient($dbForHeader, $_SESSION['idClient']);
         if ($headerIdCompte) {
             $headerImgData = recupererImageProfilCompte($dbForHeader, $headerIdCompte);
             if ($headerImgData && !empty($headerImgData['i_lien'])) {
                 $headerProfileImage = htmlspecialchars($headerImgData['i_lien']);
                 $headerProfileClass = "profile-icon-rounded";
                 $headerProfileStyle = "border-radius: 50%; object-fit: cover;";
             }
         }
    }
}
?>
<header class="site-header" role="banner">
    <div class="header-inner">
        <div class="logo-container">
            <a href="/" class="brand">
                <img src="/img/svg/logo-text.svg" alt="Alizon" class="logo" />
            </a>
        </div>

        <div class="search-container">
            <form method="GET" action="/index.php" id="headerSearchForm">
                <img src="/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                <input type="text" name="recherche" id="headerSearchInput" placeholder="Rechercher des produits..."
                    class="search-input" value="<?= htmlspecialchars($_GET['recherche'] ?? '') ?>" autocomplete="off">
                <?php if (isset($_GET['recherche']) && !empty($_GET['recherche'])): ?>
                <button type="button" id="clearSearchBtn" class="clear-search-btn"
                    aria-label="Effacer la recherche">×</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="icons-container">
            <a href="/pages/panier/index.php" class="icon-link" id="cart-icon-container">
                <img src="/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                    style="filter: invert(1) saturate(0.9);">
            </a>
            <a href="/pages/ProfilClient/index.php" class="icon-link">
                <img src="<?php echo $headerProfileImage; ?>" alt="Profile" class="<?php echo $headerProfileClass; ?>"
                    style="<?php echo $headerProfileStyle; ?>" width="40" height="40">
                <?php if (isset($_SESSION['idClient'])): ?>
                <span class="status-dot"></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>

<div class="separator"></div>