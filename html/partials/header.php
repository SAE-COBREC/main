<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        // Headers already sent: cannot start session here without causing a warning.
        // Pages that require session should call session_start() before any output.
    }
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
<!DOCTYPE html>
<script>
(function() {
    var STORAGE_KEY = 'alizon_animations_enabled';

    function readAnimationPreference() {
        try {
            var savedValue = window.localStorage.getItem(STORAGE_KEY);
            if (savedValue === null) {
                return true;
            }
            return savedValue === '1';
        } catch (error) {
            return true;
        }
    }

    function applyAnimationPreference(isEnabled) {
        document.documentElement.setAttribute('data-animations', isEnabled ? 'on' : 'off');
    }

    function setAnimationPreference(isEnabled) {
        var nextValue = !!isEnabled;
        applyAnimationPreference(nextValue);

        try {
            window.localStorage.setItem(STORAGE_KEY, nextValue ? '1' : '0');
        } catch (error) {
            // Ignore storage errors (private mode, quotas, etc.) and keep runtime state.
        }

        window.dispatchEvent(new CustomEvent('alizon:animations-changed', {
            detail: {
                enabled: nextValue
            }
        }));
    }

    applyAnimationPreference(readAnimationPreference());

    window.AlizonMotion = {
        isEnabled: function() {
            return document.documentElement.getAttribute('data-animations') !== 'off';
        },
        setEnabled: setAnimationPreference,
        storageKey: STORAGE_KEY
    };
})();
</script>
<style>
html[data-animations="off"] *,
html[data-animations="off"] *::before,
html[data-animations="off"] *::after {
    animation: none !important;
    transition: none !important;
    scroll-behavior: auto !important;
}
</style>
<header class="site-header" role="banner">
    <div class="header-inner">
        <div class="logo-container">
            <a href="/" class="brand">
                <img src="/img/svg/logo-text.svg" alt="Alizon" class="logo" />
            </a>
        </div>

        <!--barre de recherche par nom de produit-->
        <div class="search-container" style="justify-content: normal;">
            <form method="POST" class="search-form">
                <!--icône de loupe-->
                <img src="/img/svg/loupe.svg" alt="Loupe de recherche" class="fas fa-shopping-cart icon loupe-icon">
                <!--champ de saisie pour le nom de produit-->
                <input type="search" id="nomChercher" name="nomChercher"
                    placeholder="Rechercher un produit (ex: Smartphone, Pull, Bracelet...)" class="search-input"
                    value="<?= htmlspecialchars($rechercheNom ?? '') ?>" autofocus>
            </form>
        </div>


        <div class="icons-container">
            <a href="#" class="carte" id="toggle-map-btn" title="Afficher/Masquer la carte">
                <img src="/img/png/carte-et-localisation.png" alt="Carte" class="fas fa-shopping-cart icon">
            </a>
            <a href="/pages/panier/index.php" class="icon-link" id="cart-icon-container">
                <img src="/img/svg/panier.svg" alt="Panier" class="fas fa-shopping-cart icon"
                    style="filter: invert(1) saturate(0.9);">
            </a>
            <a href="/pages/ProfilClient/index.php" class="icon-link">
                <img src="<?php echo $headerProfileImage; ?>" alt="Profil" class="<?php echo $headerProfileClass; ?>"
                    style="<?php echo $headerProfileStyle; ?>" width="40" height="40">
                <?php if (isset($_SESSION['idClient'])): ?>
                <span class="status-dot"></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>
<?php include __DIR__ . '/loader.html'; ?>
<script src="/js/loader.js"></script>

<div class="separator"></div>