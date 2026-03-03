<?php 
require_once __DIR__ . '/../selectBDD.php';
require_once __DIR__ . '/../pages/fonctions.php';

$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];
$adresseDesVendeurs = getAdresseVendeur(
    $connexionBaseDeDonnees, 
    getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs)
);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Carte des vendeurs</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
    }

    /* --- Bandeau supérieur --- */
    #bandeau {
        width: 100%;
        height: 50px;
        background-color: #7171A3;
        display: flex;
        align-items: center;
        padding: 0 20px;
    }

    #titre {
        color: #FFFFFF;
        font-size: 20px;
        font-weight: bold;
        font-family: Georgia, "Times New Roman", Times, serif;
    }

    #nb-vendeurs {
        margin-left: auto;
        color: #e4e4f5;
        font-size: 14px;
    }

    /* --- Cadre principal --- */
    #cadre {
        width: 100%;
        height: calc(100vh - 50px);
        display: flex;
    }

    /* --- Encart latéral --- */
    #encart {
        width: 280px;
        min-width: 280px;
        height: 100%;
        background-color: #f4f4f4;
        padding: 20px;
        overflow-y: auto;
        border-right: 2px solid #e4e4f5;
    }

    #encart h2 {
        font-size: 16px;
        color: #7171A3;
        margin-bottom: 12px;
    }

    #encart p {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }

    #encart ul {
        padding-left: 18px;
        font-size: 13px;
        color: #555;
        line-height: 1.8;
    }

    #encart .badge {
        display: inline-block;
        background-color: #7171A3;
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 13px;
        margin-bottom: 12px;
    }

    #encart .legende {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
        font-size: 13px;
        color: #555;
    }

    #encart .legende img {
        width: 20px;
        height: 20px;
    }

    /* --- Carte Leaflet --- */
    #map {
        flex: 1;
        height: 100%;
    }

    /* Cacher l'attribution Leaflet */
    .leaflet-bottom.leaflet-right .leaflet-control-attribution {
        display: none;
    }

    /* --- Popup vendeur --- */
    .vendor-popup {
        font-family: Arial, sans-serif;
        min-width: 200px;
    }

    .vendor-popup h3 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #333;
    }

    .vendor-popup p {
        margin: 4px 0;
        font-size: 13px;
        color: #666;
    }

    .vendor-popup .address-label {
        color: #999;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vendor-popup button {
        margin-top: 10px;
        padding: 8px 12px;
        cursor: pointer;
        font-size: 13px;
        width: 100%;
        box-sizing: border-box;
        background: #7171A3;
        color: #FFFFFF;
        border: 2px solid #e4e4f5;
        border-radius: 8px;
        transition: background 0.2s, color 0.2s;
    }

    .vendor-popup button:hover {
        background-color: #FFFFFF;
        color: #7171A3;
    }

    /* --- Loader --- */
    #loader {
        position: absolute;
        top: 70px;
        left: 300px;
        background: rgba(113, 113, 163, 0.9);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        z-index: 1000;
        display: none;
    }
    </style>
</head>

<body>

    <!-- Bandeau supérieur -->
    <div id="bandeau">
        <div id="titre">🗺️ Carte des vendeurs</div>
        <div id="nb-vendeurs"></div>
    </div>

    <!-- Cadre principal : encart + carte -->
    <div id="cadre">

        <div id="encart">
            <h2>Informations</h2>
            <div class="badge" id="badge-count">Chargement...</div>
            <p>Localisation géographique des vendeurs sélectionnés dans votre liste.</p>
            <ul>
                <li>Cliquez sur un marqueur pour afficher les détails</li>
                <li>Utilisez les clusters pour naviguer</li>
                <li>Zoomez pour voir les vendeurs précisément</li>
            </ul>
            <div class="legende">
                <img src="/img/png/carte-et-localisation.png" alt="icône vendeur">
                <span>Vendeur géolocalisé</span>
            </div>
        </div>

        <div id="map"></div>
    </div>

    <!-- Indicateur de chargement -->
    <div id="loader">⏳ Géocodage en cours...</div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
    // ============================================================
    // 1. Initialisation de la carte (centrée sur la Bretagne)
    // ============================================================
    var map = L.map('map').setView([48.733333, -3.466667], 10);

    // ============================================================
    // 2. Fonds de carte (OSM + ESRI + CartoDB)
    // ============================================================
    var baselayers = {
        OSM: L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap France'
        }),
        ESRI: L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI'
            }),
        CartoDB: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
            attribution: '&copy; CartoDB'
        }),
        Satellite: L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI World Imagery'
            })
    };
    baselayers.OSM.addTo(map);

    // ============================================================
    // 3. Échelle cartographique
    // ============================================================
    L.control.scale({
        imperial: false
    }).addTo(map);

    // ============================================================
    // 4. Sélecteur de fonds de carte
    // ============================================================
    L.control.layers(baselayers, null, {
        collapsed: false,
        position: 'topright'
    }).addTo(map);

    // ============================================================
    // 5. Icône personnalisée et groupe de clusters
    // ============================================================
    var iconVendeur = L.icon({
        iconUrl: '/img/png/carte-et-localisation.png',
        iconSize: [20, 20],
        iconAnchor: [20, 20],
        popupAnchor: [-10, -20]
    });

    var markers = L.markerClusterGroup();
    map.addLayer(markers);

    // ============================================================
    // 6. Données vendeurs (injectées depuis PHP)
    // ============================================================
    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

    // Mise à jour badge et bandeau
    var total = Array.isArray(adressesVendeurs) ? adressesVendeurs.length : 0;
    document.getElementById('badge-count').textContent = total + ' vendeur(s) trouvé(s)';
    document.getElementById('nb-vendeurs').textContent = total + ' vendeur(s) dans la liste';

    // ============================================================
    // 7. Fonction de géocodage et ajout de marqueurs
    // ============================================================
    var loader = document.getElementById('loader');
    var geocodageEnCours = 0;

    function ajouterMarkerVendeur(vendeur) {
        var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || '';
        var nom = vendeur.nom || vendeur.denomination || vendeur.v_denomination || 'Vendeur';

        if (!adresse || adresse.trim() === '') {
            return;
        }

        var adresseEncodee = encodeURIComponent(adresse);
        var urlNominatim = 'https://nominatim.openstreetmap.org/search?q=' + adresseEncodee + '&format=json&limit=1';

        geocodageEnCours++;
        loader.style.display = 'block';

        fetch(urlNominatim)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    var lat = parseFloat(data[0].lat);
                    var lon = parseFloat(data[0].lon);

                    var newMarker = L.marker([lat, lon], {
                        icon: iconVendeur
                    });

                    // Contenu HTML de la popup (sécurisé XSS)
                    var nomSafe = nom.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    var adresseSafe = adresse.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    var nomAttr = nom.replace(/"/g, '&quot;');

                    var popupContent =
                        '<div class="vendor-popup">' +
                        '<h3>' + nomSafe + '</h3>' +
                        '<p class="address-label">Adresse</p>' +
                        '<p>' + adresseSafe + '</p>' +
                        '<button class="btn-voir-produits" data-vendeur="' + nomAttr + '">' +
                        'Voir les produits' +
                        '</button>' +
                        '</div>';

                    newMarker.bindPopup(popupContent);

                    // Gestion du clic sur "Voir les produits"
                    newMarker.on('popupopen', function() {
                        var btn = document.querySelector('.leaflet-popup-content .btn-voir-produits');
                        if (btn) {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var nomVendeur = this.getAttribute('data-vendeur');
                                var searchInput = document.getElementById('searchVendeur');
                                if (searchInput) {
                                    searchInput.value = nomVendeur;
                                    var filterForm = document.getElementById('filterForm');
                                    if (filterForm) filterForm.submit();
                                }
                            });
                        }
                    });

                    markers.addLayer(newMarker);
                } else {
                    console.warn('Aucun résultat de géocodage pour :', adresse);
                }
            })
            .catch(error => console.error('Erreur géocodage pour ' + adresse + ':', error))
            .finally(() => {
                geocodageEnCours--;
                if (geocodageEnCours === 0) {
                    loader.style.display = 'none';
                }
            });
    }

    // ============================================================
    // 8. Lancement du géocodage pour chaque vendeur
    // ============================================================
    if (Array.isArray(adressesVendeurs)) {
        adressesVendeurs.forEach(function(vendeur) {
            if (vendeur && typeof vendeur === 'object') {
                ajouterMarkerVendeur(vendeur);
            }
        });
    }
    </script>

</body>

</html>