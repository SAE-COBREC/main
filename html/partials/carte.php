<?php 
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

    <!-- Style personnalisé -->
    <link rel="stylesheet" href="/styles/Carte/styleCarte.css">

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

    <script>
    // ============================================
    // INITIALISATION DE LA CARTE
    // ============================================

    var map = L.map('map').setView([48.2500, -2.7500], 8);

    //variable qui stocke la position GPS de l'utilisateur
    var positionUtilisateur = null;

    // ============================================
    // COUCHES DE FOND DE CARTE
    // ============================================

    var baselayers = {
        "ESRI Topo": L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI'
            }),
        "OpenStreetMap": L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap France'
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
        collapsed: true,
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
        iconUrl: '/img/png/carte-et-localisation.png',
        iconSize: [33, 33],
        iconAnchor: [16, 33],
        popupAnchor: [0, -30]
    });

    //données vendeurs transmises depuis PHP
    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

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
            '">Voir les produits</button>' +
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
            '<img src="/img/png/carte-et-localisation.png" style="width:20px;height:20px;margin-right:6px;">' +
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
            '';
        L.DomEvent.disableClickPropagation(div);
        return div;
    };

    legende.addTo(map);
    </script>
</body>

</html>