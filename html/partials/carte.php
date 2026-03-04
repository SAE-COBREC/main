<?php 
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

require_once __DIR__ . '/../selectBDD.php';
require_once __DIR__ . '/../pages/fonctions.php';

$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// TRAITEMENT AJAX : SAUVEGARDE DES COORDONNÉES
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_coords') {
    header('Content-Type: application/json; charset=utf-8');
    $idAdresse = isset($_POST['id_adresse']) ? (int) $_POST['id_adresse'] : 0;
    $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
    $lon = isset($_POST['lon']) ? (float) $_POST['lon'] : null;

    if ($idAdresse > 0 && $lat !== null && $lon !== null) {
        try {
            $stmt = $connexionBaseDeDonnees->prepare(
                "UPDATE cobrec1._adresse SET latitude = :lat, longitude = :lon WHERE id_adresse = :id"
            );
            $stmt->execute([':lat' => $lat, ':lon' => $lon, ':id' => $idAdresse]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    }
    exit;
}

// ============================================
// CHARGEMENT DES DONNÉES VENDEURS
// ============================================

$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];
$idVendeurs = getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs);
$adresseDesVendeurs = getAdresseVendeur($connexionBaseDeDonnees, $idVendeurs);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

    <!-- Locate control CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />

    <!-- Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <!-- Fullscreen CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen@2.4.0/Control.FullScreen.css" />

    <!-- Style personnalisé -->
    <link rel="stylesheet" href="/styles/Carte/style.css">

    <style>
    /* ============================================
           PANNEAU ITINÉRAIRE (Routing Machine)
        ============================================ */
    .leaflet-routing-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        padding: 0;
        min-width: 260px;
        max-width: 300px;
        font-family: Arial, sans-serif;
        font-size: 13px;
        max-height: 380px;
        overflow-y: auto;
    }

    .leaflet-routing-alt {
        padding: 10px 14px;
    }

    .leaflet-routing-alt h2 {
        font-size: 14px;
        font-weight: bold;
        margin: 0 0 2px 0;
        color: #0066ff;
    }

    .leaflet-routing-alt h3 {
        font-size: 12px;
        color: #666;
        margin: 0 0 8px 0;
    }

    .leaflet-routing-alt table {
        width: 100%;
        border-collapse: collapse;
    }

    .leaflet-routing-alt tr td {
        padding: 5px 6px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
        line-height: 1.4;
    }

    .leaflet-routing-alt tr td:first-child {
        font-size: 16px;
        width: 28px;
        text-align: center;
        color: #0066ff;
    }

    .leaflet-routing-alt tr td:last-child {
        color: #999;
        font-size: 11px;
        text-align: right;
        white-space: nowrap;
    }

    .leaflet-routing-alt tr:hover td {
        background: #f5f9ff;
    }

    .leaflet-routing-geocoder input {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px 8px;
        font-size: 12px;
        box-sizing: border-box;
        margin-bottom: 4px;
    }

    .leaflet-routing-collapse-btn {
        background: #0066ff;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 3px 8px;
        cursor: pointer;
        font-size: 12px;
        float: right;
        margin: 4px;
    }

    /* ============================================
           LÉGENDE
        ============================================ */
    .legende-carte {
        background: white;
        padding: 10px 14px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        font-size: 13px;
        line-height: 1.6;
        min-width: 160px;
    }

    .legende-carte h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: bold;
        border-bottom: 1px solid #ddd;
        padding-bottom: 4px;
    }

    .legende-item {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
    }

    /* ============================================
           BOUTONS POPUP VENDEUR
        ============================================ */
    .btn-voir-produits {
        display: block;
        width: 100%;
        padding: 6px 10px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .btn-voir-produits:hover {
        background: #218838;
    }

    .btn-itineraire {
        display: block;
        width: 100%;
        padding: 6px 10px;
        background: #0066ff;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
    }

    .btn-itineraire:hover {
        background: #0050cc;
    }
    </style>
</head>

<body>

    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- MarkerCluster JS -->
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <!-- Locate control JS -->
    <script src="https://unpkg.com/leaflet.locatecontrol/dist/L.Control.Locate.min.js"></script>

    <!-- Routing Machine JS -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <!-- Fullscreen JS -->
    <script src="https://unpkg.com/leaflet.fullscreen@2.4.0/Control.FullScreen.js"></script>

    <script>
    // ============================================
    // INITIALISATION DE LA CARTE
    // ============================================

    var map = L.map('map', {
        fullscreenControl: true,
        fullscreenControlOptions: {
            position: 'topleft',
            title: 'Plein écran',
            titleCancel: 'Quitter le plein écran'
        }
    }).setView([48.2500, -2.7500], 8);

    //variable qui stocke l'itinéraire actif
    var routingControl = null;

    //variable qui stocke la position GPS de l'utilisateur
    var positionUtilisateur = null;

    // ============================================
    // COUCHES DE FOND DE CARTE
    // ============================================

    var baselayers = {
        "OpenStreetMap": L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap France'
        }),
        "ESRI Topo": L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI'
            }),
        "CartoDB": L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
            attribution: '&copy; CartoDB'
        }),
        "Satellite": L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI World Imagery'
            }),
        "Mode Sombre": L.tileLayer(
            'https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png', {
                maxZoom: 20,
                attribution: '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a> &copy; OpenStreetMap contributors'
            })
    };

    baselayers["ESRI Topo"].addTo(map);

    // ============================================
    // LAYERS OVERLAY (vendeurs + villes)
    // ============================================

    var markersVendeurs = L.markerClusterGroup();
    var layerVilles = L.layerGroup();

    map.addLayer(markersVendeurs);

    var overlays = {
        "Vendeurs": markersVendeurs,
        "Afficher les villes": layerVilles
    };

    L.control.layers(baselayers, overlays, {
        collapsed: false,
        position: 'topright'
    }).addTo(map);

    L.control.scale({
        imperial: false
    }).addTo(map);

    // ============================================
    // LOCATE CONTROL
    // ============================================

    var lc = L.control.locate({
        position: 'topleft',
        strings: {
            title: "Afficher ma position"
        },
        flyTo: true,
        keepCurrentZoomLevel: false,
        locateOptions: {
            enableHighAccuracy: true
        }
    }).addTo(map);

    //mémorise la position utilisateur dès qu'elle est connue via le bouton Locate
    map.on('locationfound', function(e) {
        positionUtilisateur = e.latlng;
    });

    // ============================================
    // ICÔNE VENDEUR
    // ============================================

    var iconVendeur = L.icon({
        iconUrl: '/img/png/badge-bretagne.png',
        iconSize: [33, 33],
        iconAnchor: [16, 33],
        popupAnchor: [0, -30]
    });

    //données vendeurs transmises depuis PHP
    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

    // ============================================
    // ROUTING MACHINE
    // ============================================

    //affiche l'itinéraire entre la position utilisateur et le vendeur choisi
    function afficherItineraire(latVendeur, lonVendeur, nomVendeur) {
        //supprime l'itinéraire précédent s'il existe
        if (routingControl !== null) {
            map.removeControl(routingControl);
            routingControl = null;
        }

        //vérifie que la position de l'utilisateur est connue
        if (!positionUtilisateur) {
            alert('Cliquez d\'abord sur "Afficher ma position" pour activer votre GPS.');
            return;
        }

        //crée le contrôle d'itinéraire OSRM (gratuit, sans clé API)
        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(positionUtilisateur.lat, positionUtilisateur.lng),
                L.latLng(latVendeur, lonVendeur)
            ],
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                language: 'fr'
            }),
            collapsible: true,
            lineOptions: {
                styles: [{
                    color: '#0066ff',
                    weight: 5,
                    opacity: 0.8
                }]
            },
            createMarker: function(i, waypoint) {
                var label = i === 0 ? '<b>Vous êtes ici</b>' : '<b>' + nomVendeur + '</b>';
                return L.marker(waypoint.latLng).bindPopup(label);
            }
        }).addTo(map);
    }

    // ============================================
    // MARQUEURS VENDEURS
    // ============================================

    function placerMarker(lat, lon, nom, adresse) {
        var newMarker = L.marker([lat, lon], {
            icon: iconVendeur
        });

        //popup avec boutons "Voir les produits" et "Itinéraire"
        var popupContent = '<div class="vendor-popup">' +
            '<h3>' + nom.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3>' +
            '<p><b>Adresse :</b><br>' + adresse.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
            '<button class="btn-voir-produits" data-vendeur="' + nom.replace(/"/g, '&quot;') +
            '">🛒 Voir les produits</button>' +
            '<button class="btn-itineraire" data-lat="' + lat + '" data-lon="' + lon + '" data-nom="' + nom.replace(
                /"/g, '&quot;') + '">🗺️ Itinéraire</button>' +
            '</div>';

        newMarker.bindPopup(popupContent);

        newMarker.on('popupopen', function() {
            //bouton "Voir les produits"
            var btnProduits = document.querySelector('.leaflet-popup-content .btn-voir-produits');
            if (btnProduits) {
                btnProduits.onclick = function() {
                    var nomVendeur = this.getAttribute('data-vendeur');
                    var searchInput = document.getElementById('searchVendeur');
                    if (searchInput) {
                        searchInput.value = nomVendeur;
                        var form = document.getElementById('filterForm');
                        if (form) form.submit();
                    }
                };
            }

            //bouton "Itinéraire"
            var btnIti = document.querySelector('.leaflet-popup-content .btn-itineraire');
            if (btnIti) {
                btnIti.onclick = function() {
                    var latV = parseFloat(this.getAttribute('data-lat'));
                    var lonV = parseFloat(this.getAttribute('data-lon'));
                    var nomV = this.getAttribute('data-nom');
                    map.closePopup();
                    afficherItineraire(latV, lonV, nomV);
                };
            }
        });

        markersVendeurs.addLayer(newMarker);
    }

    function sauvegarderCoords(idAdresse, lat, lon) {
        var formData = new FormData();
        formData.append('action', 'save_coords');
        formData.append('id_adresse', idAdresse);
        formData.append('lat', lat);
        formData.append('lon', lon);
        fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .catch(err => console.warn('Erreur sauvegarde:', err));
    }

    function ajouterMarkerVendeur(vendeur) {
        var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || '';
        var nom = vendeur.nom || vendeur.denomination || vendeur.v_denomination || 'Vendeur';
        var idAdresse = vendeur.id_adresse || null;

        if (!adresse.trim()) return;

        if (vendeur.latitude && vendeur.longitude) {
            placerMarker(vendeur.latitude, vendeur.longitude, nom, adresse);
        } else {
            fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(adresse) +
                    '&format=json&limit=1')
                .then(r => r.json())
                .then(data => {
                    if (data.length > 0) {
                        var lat = parseFloat(data[0].lat);
                        var lon = parseFloat(data[0].lon);
                        placerMarker(lat, lon, nom, adresse);
                        if (idAdresse) sauvegarderCoords(idAdresse, lat, lon);
                    }
                });
        }
    }

    if (Array.isArray(adressesVendeurs)) {
        adressesVendeurs.forEach(v => {
            if (v) ajouterMarkerVendeur(v);
        });
    }

    // ============================================
    // VILLES DE BRETAGNE
    // ============================================

    var pointsBretagne = [{
            nom: "Rennes",
            lat: 48.1173,
            lon: -1.6778
        },
        {
            nom: "Brest",
            lat: 48.3904,
            lon: -4.4861
        },
        {
            nom: "Quimper",
            lat: 47.9960,
            lon: -4.1025
        },
        {
            nom: "Lorient",
            lat: 47.7482,
            lon: -3.3702
        },
        {
            nom: "Vannes",
            lat: 47.6559,
            lon: -2.7603
        },
        {
            nom: "Saint-Malo",
            lat: 48.6493,
            lon: -2.0260
        },
        {
            nom: "Saint-Brieuc",
            lat: 48.5144,
            lon: -2.7654
        },
        {
            nom: "Fougères",
            lat: 48.3524,
            lon: -1.2027
        },
        {
            nom: "Concarneau",
            lat: 47.8703,
            lon: -3.9173
        },
        {
            nom: "Morlaix",
            lat: 48.5779,
            lon: -3.8280
        },
        {
            nom: "Lannion",
            lat: 48.7500,
            lon: -3.4500
        }
    ];

    pointsBretagne.forEach(function(p) {
        var m = L.marker([p.lat, p.lon]);
        m.bindTooltip('<b>' + p.nom + '</b>', {
            permanent: true,
            direction: 'top',
            offset: [-15, -10]
        });
        m.addTo(layerVilles);
    });

    // ============================================
    // LÉGENDE
    // ============================================

    var legende = L.control({
        position: 'bottomright'
    });

    legende.onAdd = function() {
        var div = L.DomUtil.create('div', 'legende-carte');
        div.innerHTML =
            '<h4>Légende</h4>' +
            '<div class="legende-item">' +
            '<img src="/img/png/badge-bretagne.png" style="width:20px;height:20px;margin-right:6px;">' +
            '<span>Vendeur</span>' +
            '</div>' +
            '<div class="legende-item">' +
            '<img src="https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png" style="width:13px;height:21px;margin-right:6px;">' +
            '<span>Ville de Bretagne</span>' +
            '</div>' +
            '<div class="legende-item">' +
            '<span style="display:inline-block;width:14px;height:14px;background:#2674c8;border-radius:50%;margin-right:6px;border:2px solid white;box-sizing:border-box;"></span>' +
            '<span>Votre position</span>' +
            '</div>' +
            '<div class="legende-item">' +
            '<span style="display:inline-block;width:20px;height:4px;background:#0066ff;margin-right:6px;border-radius:2px;"></span>' +
            '<span>Itinéraire</span>' +
            '</div>';
        L.DomEvent.disableClickPropagation(div);
        return div;
    };

    legende.addTo(map);
    </script>
</body>

</html>