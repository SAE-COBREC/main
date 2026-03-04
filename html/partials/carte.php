<?php 
require_once __DIR__ . '/../selectBDD.php';
require_once __DIR__ . '/../pages/fonctions.php';

$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// Endpoint AJAX : sauvegarde des coordonnées géocodées dans la BDD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_coords') {
    header('Content-Type: application/json; charset=utf-8');
    $idAdresse = isset($_POST['id_adresse']) ? (int) $_POST['id_adresse'] : 0;
    $lat       = isset($_POST['lat'])        ? (float) $_POST['lat']        : null;
    $lon       = isset($_POST['lon'])        ? (float) $_POST['lon']        : null;

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

$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];

$adresseDesVendeurs = getAdresseVendeur($connexionBaseDeDonnees, getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Cluster -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="/styles/Carte/style.css">

</head>

<body>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
    var map = L.map('map').setView([48.2500, -2.7500], 8);

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

    L.control.scale({
        imperial: false
    }).addTo(map);

    L.control.layers(baselayers, null, {
        collapsed: false,
        position: 'topright'
    }).addTo(map);

    var iconVendeur = L.icon({
        iconUrl: '/img/png/badge-bretagne.png',
        iconSize: [33, 33],
        iconAnchor: [20, 20],
        popupAnchor: [-10, -20]
    });

    var markers = L.markerClusterGroup();
    map.addLayer(markers);

    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

    function placerMarker(lat, lon, nom, adresse) {
        var newMarker = L.marker([lat, lon], {
            icon: iconVendeur
        });

        var popupContent = '<div class="vendor-popup">' +
            '<h3>' + nom.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3>' +
            '<p class="address-label">Adresse :</p>' +
            '<p>' + adresse.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
            '<button class="btn-voir-produits" data-vendeur="' + nom.replace(/"/g, '&quot;') +
            '">Voir les produits</button>' +
            '</div>';

        newMarker.bindPopup(popupContent);

        newMarker.on('popupopen', function() {
            var btn = document.querySelector('.leaflet-popup-content .btn-voir-produits');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var nomVendeur = this.getAttribute('data-vendeur');
                    var searchVendeurInput = document.getElementById('searchVendeur');
                    if (searchVendeurInput) {
                        searchVendeurInput.value = nomVendeur;
                        var filterForm = document.getElementById('filterForm');
                        if (filterForm) {
                            filterForm.submit();
                        }
                    }
                });
            }
        });

        markers.addLayer(newMarker);
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
            .catch(function(err) {
                console.warn('Impossible de sauvegarder les coordonnées:', err);
            });
    }

    function ajouterMarkerVendeur(vendeur) {
        var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || '';
        var nom = vendeur.nom || vendeur.denomination || vendeur.v_denomination || 'Vendeur';
        var idAdresse = vendeur.id_adresse || null;

        if (!adresse || adresse.trim() === '') {
            return;
        }

        // Coordonnées déjà géocodées et stockées en BDD
        if (vendeur.latitude !== null && vendeur.latitude !== undefined &&
            vendeur.longitude !== null && vendeur.longitude !== undefined) {
            placerMarker(vendeur.latitude, vendeur.longitude, nom, adresse);
            return;
        }

        // Sinon : géocodage Nominatim puis sauvegarde en BDD
        var adresseEncodee = encodeURIComponent(adresse);
        var urlNominatim = 'https://nominatim.openstreetmap.org/search?q=' + adresseEncodee + '&format=json&limit=1';

        fetch(urlNominatim)
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data && data.length > 0) {
                    var lat = parseFloat(data[0].lat);
                    var lon = parseFloat(data[0].lon);
                    placerMarker(lat, lon, nom, adresse);
                    // Sauvegarder en BDD pour éviter de recalculer
                    if (idAdresse) {
                        sauvegarderCoords(idAdresse, lat, lon);
                    }
                } else {
                    console.warn('Aucun résultat de géocodage pour:', adresse);
                }
            })
            .catch(function(error) {
                console.error('Erreur de géocodage pour ' + adresse + ':', error);
            });
    }

    if (Array.isArray(adressesVendeurs)) {
        adressesVendeurs.forEach(function(vendeur) {
            if (vendeur && typeof vendeur === 'object') {
                ajouterMarkerVendeur(vendeur);
            }
        });
    }

    // 10 points fixes en Bretagne
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

    pointsBretagne.forEach(function(point) {
        var marker = L.marker([point.lat, point.lon]);

        marker.bindTooltip('<b>' + point.nom + '</b>', {
            permanent: true,
            direction: 'top',
            offset: [-15, -10]
        });

        markers.addLayer(marker);
    });
    </script>
</body>

</html>