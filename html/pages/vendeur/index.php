<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../../pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// RÉCUPÉRATION DU PARAMÈTRE VENDEUR
// ============================================

//récupère la dénomination du vendeur passée en paramètre GET
$denominationVendeur = trim($_GET['denomination'] ?? '');

//redirige vers l'accueil si aucune dénomination n'est fournie
if ($denominationVendeur === '') {
    header('Location: /');
    exit;
}

// ============================================
// CHARGEMENT DES INFORMATIONS DU VENDEUR
// ============================================

//charge les informations du vendeur depuis la base de données
$informationsVendeur = chargerInformationsVendeur($connexionBaseDeDonnees, $denominationVendeur);

//affiche une page 404 si le vendeur est introuvable
if (!$informationsVendeur) {
    http_response_code(404);
    include __DIR__ . '/../produit/not-found.php';
    exit;
}

// ============================================
// CHARGEMENT DES PRODUITS DU VENDEUR
// ============================================

//récupère tous les produits associés à ce vendeur
$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $informationsVendeur['denomination']);
//compte le nombre de produits en ligne
$nombreProduits = count($listeProduits);

// ============================================
// CALCUL DES STATISTIQUES DU VENDEUR
// ============================================

//initialise la note moyenne et le nombre d'avis
$noteMoyenneVendeur = 0.0;
$nombreAvisTotal    = 0;

//calcule les statistiques seulement si le vendeur a des produits
if ($nombreProduits > 0) {
    $statistiques       = calculerStatistiquesVendeur($connexionBaseDeDonnees, $informationsVendeur['id_vendeur']);
    $noteMoyenneVendeur = $statistiques['note'];
    $nombreAvisTotal    = $statistiques['nb'];
}

// ============================================
// GESTION DE LA SESSION ET DU PANIER
// ============================================

//récupère l'ID du client s'il est connecté
$idClient = $_SESSION['idClient'] ?? null;
//initialise le panier (crée ou récupère selon l'état de connexion)
$idPanier = gererPanierClient($connexionBaseDeDonnees, $idClient);

// ============================================
// TRAITEMENT AJAX : AJOUT AU PANIER
// ============================================

//vérifie si c'est une requête d'ajout au panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    traiterAjaxAjoutPanierVendeur($connexionBaseDeDonnees, $idClient, $idPanier);
}

// ============================================
// DONNÉES D'AFFICHAGE
// ============================================

//construit l'adresse complète du vendeur pour le géocodage
$adresseComplete = construireAdresseCompleteVendeur($informationsVendeur);

// Récupération du thème de daltonisme depuis la session
$current_theme = isset($_SESSION['colorblind_mode']) ? $_SESSION['colorblind_mode'] : 'default';
?>

<!doctype html>
<html lang="fr" <?php echo ($current_theme !== 'default') ? 'data-theme="' . htmlspecialchars($current_theme) . '"' : ''; ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($informationsVendeur['denomination']) ?> – Alizon</title>

    <!--charge l'icône du site-->
    <link rel="icon" type="image/png" href="/img/favicon.svg">

    <!--charge les feuilles de style CSS-->
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
    <link rel="stylesheet" href="/styles/Vendeur/style.css">
    <link rel="stylesheet" href="/styles/Star/star.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <script src="../../js/accessibility.js"></script>
</head>

<body>
    <?php
    //inclut l'en-tête de la page
    include __DIR__ . '/../../partials/header.php';
    ?>

    <!--conteneur principal de la page vendeur-->
    <main>

<!doctype html>
<html lang="fr" <?php echo ($current_theme !== 'default') ? 'data-theme="' . htmlspecialchars($current_theme) . '"' : ''; ?>>

        <!--lien de retour vers l'accueil-->
        <a href="/">
            ← Retour à l'accueil
        </a>

        <!--carte de profil du vendeur-->
        <section>

            <!--affiche l'avatar du vendeur ou un placeholder-->
            <?php if (!empty($informationsVendeur['image'])): ?>
            <img src="<?= htmlspecialchars($informationsVendeur['image']) ?>"
                alt="<?= htmlspecialchars($informationsVendeur['denomination']) ?>">
            <?php else: ?>
            <figure>
                <img src="/img/svg/market.svg" alt="Vendeur">
            </figure>
            <?php endif; ?>

            <!--informations textuelles du vendeur-->
            <div>

                <!--nom de la dénomination du vendeur-->
                <h1><?= htmlspecialchars($informationsVendeur['denomination']) ?></h1>

                <!--affiche la raison sociale si elle existe-->
                <?php if (!empty($informationsVendeur['raison_sociale'])): ?>
                <small><?= htmlspecialchars($informationsVendeur['raison_sociale']) ?></small>
                <?php endif; ?>

                <!--affiche la note moyenne si elle existe-->
                <?php if ($noteMoyenneVendeur > 0): ?>
                <p>
                    <!--affiche les étoiles de la note moyenne-->
                    <?= afficherEtoilesVendeur($noteMoyenneVendeur, 18) ?>
                    <!--affiche la valeur numérique de la note-->
                    <strong><?= number_format($noteMoyenneVendeur, 1, ',', '') ?>/5</strong>
                    <!--affiche le nombre d'avis-->
                    <span style="color:#9ca3af">(<?= $nombreAvisTotal ?> avis)</span>
                </p>
                <?php endif; ?>

                <!--affiche la localisation du vendeur si elle existe-->
                <?php if (!empty($informationsVendeur['ville'])): ?>
                <address>
                    <img src="/img/png/carte-et-localisation.png" alt="" width="14" onerror="this.style.display='none'">
                    <!--affiche le code postal s'il est disponible-->
                    <?php if (!empty($informationsVendeur['code_postal'])): ?>
                    <?= htmlspecialchars($informationsVendeur['code_postal']) ?> –
                    <?php endif; ?>
                    <?= htmlspecialchars($informationsVendeur['ville']) ?>
                </address>
                <?php endif; ?>

                <!--chips de statistiques rapides-->
                <footer>
                    <!--chip affichant le nombre de produits en ligne-->
                    <span>
                        <img src="/img/svg/market.svg" alt="">
                        <?= $nombreProduits ?> produit<?= $nombreProduits > 1 ? 's' : '' ?> en ligne
                    </span>
                    <!--affiche le numéro SIREN si disponible-->
                    <?php if (!empty($informationsVendeur['siren'])): ?>
                    <span>
                        SIREN : <?= htmlspecialchars($informationsVendeur['siren']) ?>
                    </span>
                    <?php endif; ?>
                </footer>

            </div>
        </section>

        <?php if (!empty($adresseComplete)): ?>
        <div id="map-vendeur" style="width:100%;height:350px;border-radius:12px;overflow:hidden;margin:1.5rem 0;"></div>
        <?php endif; ?>

        <!--titre de la section produits-->
        <h2>
            Produits de <?= htmlspecialchars($informationsVendeur['denomination']) ?>
            <span>(<?= $nombreProduits ?>)</span>
        </h2>

        <!--affiche un message si le vendeur n'a aucun produit-->
        <?php if (empty($listeProduits)): ?>
        <p>Ce vendeur n'a aucun produit en ligne pour le moment.</p>
        <?php else: ?>

        <!--grille des produits du vendeur-->
        <ul>
            <!--boucle sur tous les produits du vendeur-->
            <?php foreach ($listeProduits as $produitCourant):
                //vérifie si le produit est en rupture de stock
                $estEnRupture = $produitCourant['p_stock'] <= 0;
                //récupère le pourcentage de réduction
                $discount = (float) ($produitCourant['reduction_pourcentage'] ?? 0);
                //vérifie s'il y a une réduction
                $possedePourcentageRemise = $discount > 0;
                //calcule le prix après réduction
                $prixDiscount = $possedePourcentageRemise ? $produitCourant['p_prix'] * (1 - $discount / 100) : $produitCourant['p_prix'];
                //calcule le prix final TTC
                $prixFinal = calcPrixTVA($produitCourant['tva'], $prixDiscount);
                //calcule le prix original TTC
                $prixOriginalTTC = calcPrixTVA($produitCourant['tva'], $produitCourant['p_prix']);
                //arrondit la note moyenne
                $noteArrondie = (int) round($produitCourant['note_moyenne'] ?? 0);
                //vérifie si le produit est en promotion
                $estEnPromotion = !empty($produitCourant['estenpromo']);
                //construit l'URL de l'image du produit
                $urlImage = str_replace('html/img/photo', '/img/photo', $produitCourant['image_url'] ?? '/img/default-product.jpg');
                //récupère l'origine du produit
                $origineProduit = recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
            ?>
            <!--carte de produit cliquable-->
            <li>
                <article <?= $estEnRupture ? 'data-rupture' : '' ?> <?= $estEnPromotion ? 'data-promo' : '' ?>
                    onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">

                    <div>
                        <!--image du produit-->
                        <img src="<?= htmlspecialchars($urlImage) ?>"
                            alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>">
                        <!--affiche le badge de réduction s'il y en a une-->
                        <?php if ($possedePourcentageRemise): ?>
                        <mark>-<?= round($discount) ?>%</mark>
                        <?php endif; ?>
                        <!--affiche le badge Bretagne si le produit est d'origine bretonne-->
                        <?php if ($origineProduit === 'Bretagne'): ?>
                        <span><img src="/img/png/badge-bretagne.png" alt="Bretagne"></span>
                        <?php endif; ?>
                        <!--affiche le message de rupture de stock si nécessaire-->
                        <?php if ($estEnRupture): ?>
                        <div>Rupture de stock</div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <!--nom du produit-->
                        <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>

                        <div>
                            <span>
                                <?php 
                                // On calcule l'état pour chaque étoile de 1 à 5
                                for ($i = 1; $i <= 5; $i++):
                                    if ($noteArrondie >= $i) {
                                        $s = 'full';
                                    } elseif ($noteArrondie >= $i - 0.5) {
                                        $s = 'alf';
                                    } else {
                                        $s = 'empty';
                                    }
                                ?>
                                    <span class="star-icon medium <?= $s ?>"></span>
                                <?php endfor; ?>
                            </span>
                            <span>(<?= $produitCourant['nombre_avis'] ?? 0 ?>)</span>
                        </div>

                        <div>
                            <span>
                                <!--affiche le prix barré s'il y a une réduction-->
                                <?php if ($possedePourcentageRemise): ?>
                                <span style="text-decoration:line-through;color:#999;margin-right:5px;font-size:1.2em;">
                                    <?= number_format($prixOriginalTTC, 2, ',', ' ') ?>€
                                </span>
                                <?php endif; ?>
                            </span>
                            <!--affiche le prix final TTC-->
                            <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                        </div>

                        <div>
                            <!--informations du vendeur (non cliquable sur cette page)-->
                            <div style="cursor:default;">
                                <img src="/img/svg/market.svg" alt="Vendeur">
                                <span><?= htmlspecialchars($informationsVendeur['denomination']) ?></span>
                            </div>
                            <!--bouton pour ajouter au panier-->
                            <button <?= $estEnRupture ? 'disabled' : '' ?>
                                onclick="event.stopPropagation(); ajouterAuPanier(<?= $produitCourant['id_produit'] ?>)">
                                <?php if ($estEnRupture): ?>
                                    Indisponible
                                <?php else: ?>
                                    <span class="icon-panier-dynamic"></span> Ajouter au panier
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                </article>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

    </main>
    <!--/main-->

    <?php
    //inclut le pied de page
    include __DIR__ . '/../../partials/footer.html';
    //inclut le système de notifications toast
    include __DIR__ . '/../../partials/toast.html';
    //inclut les modales
    include __DIR__ . '/../../partials/modal.html';
    ?>

    <!--charge le script pour les notifications-->
    <script src="/js/notifications.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
    //fonction d'ajout au panier — identique à la page d'accueil
    function ajouterAuPanier(idProduit) {
        //crée un objet pour envoyer les données au serveur
        var formData = new FormData();
        formData.append('action', 'ajouter_panier');
        formData.append('idProduit', idProduit);
        formData.append('quantite', 1);

        //envoie la requête AJAX au serveur
        fetch(window.location.href, {
                method: 'POST',
                body: formData,
                noLoader: true
            })
            //gère la réponse de manière robuste (texte -> tentative JSON)
            .then(async function(response) {
                var text = await response.text();
                try {
                    var data = JSON.parse(text);
                    return data;
                } catch (err) {
                    //affiche la réponse brute pour débogage si elle n'est pas du JSON valide
                    console.error('Réponse serveur non JSON:', text);
                    window.notify ? notify('Erreur serveur: réponse invalide', 'error') : alert(
                        'Erreur serveur: réponse invalide');
                    throw new Error('Invalid JSON response');
                }
            })
            //traite la réponse JSON du serveur
            .then(function(data) {
                var message = data && data.message ? data.message : 'Réponse inconnue';
                var type = data && data.success ? 'success' : 'error';
                window.notify ? notify(message, type) : alert((type === 'success' ? '✓ ' : '✗ ') + message);
            })
            .catch(function(error) {
                console.error('Erreur ajout au panier:', error);
                if (!error.message || error.message === 'Invalid JSON response') return;
                window.notify ? notify('Erreur lors de l\'ajout au panier', 'error') : alert(
                    'Erreur lors de l\'ajout au panier');
            });
    }


    (function() {
        var nomVendeur = <?= json_encode($informationsVendeur['denomination']) ?>;
        var adresse = <?= json_encode($adresseComplete) ?>;
        var idAdresse = <?= json_encode($informationsVendeur['id_adresse'] ?? null) ?>;
        var latSaved =
            <?= json_encode(isset($informationsVendeur['latitude'])  && $informationsVendeur['latitude']  !== null ? (float)$informationsVendeur['latitude']  : null) ?>;
        var lonSaved =
            <?= json_encode(isset($informationsVendeur['longitude']) && $informationsVendeur['longitude'] !== null ? (float)$informationsVendeur['longitude'] : null) ?>;

        var iconVendeur = L.icon({
            iconUrl: '/img/png/carte-et-localisation.png',
            iconSize: [33, 33],
            iconAnchor: [16, 33],
            popupAnchor: [0, -30]
        });

        function initMap(lat, lon) {
            var map = L.map('map-vendeur').setView([lat, lon], 14);

            L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                    attribution: '&copy; ESRI'
                }).addTo(map);

            L.marker([lat, lon], {
                    icon: iconVendeur
                })
                .bindPopup('<b>' + nomVendeur.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</b><br>' + adresse
                    .replace(/</g, '&lt;').replace(/>/g, '&gt;'))
                .addTo(map)
                .openPopup();
        }

        function sauvegarderCoords(lat, lon) {
            if (!idAdresse) return;
            var fd = new FormData();
            fd.append('action', 'save_coords');
            fd.append('id_adresse', idAdresse);
            fd.append('lat', lat);
            fd.append('lon', lon);
            fetch('<?= htmlspecialchars('/html/partials/carte.php') ?>', {
                    method: 'POST',
                    body: fd
                })
                .catch(function(e) {
                    console.warn('Sauvegarde coords échouée', e);
                });
        }

        if (latSaved !== null && lonSaved !== null) {
            initMap(latSaved, lonSaved);
        } else {
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(adresse) +
                    '&format=json&limit=1')
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.length > 0) {
                        var lat = parseFloat(data[0].lat);
                        var lon = parseFloat(data[0].lon);
                        initMap(lat, lon);
                        sauvegarderCoords(lat, lon);
                    } else {
                        document.getElementById('map-vendeur').style.display = 'none';
                    }
                })
                .catch(function() {
                    document.getElementById('map-vendeur').style.display = 'none';
                });
        }
    })();
    </script>

</body>

</html>