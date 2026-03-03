<?php 
require_once __DIR__ . '/../selectBDD.php';
require_once __DIR__ . '/../pages/fonctions.php';

$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

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

    <style>
    .marker-cluster {
        background-color: #7171A3;
    }

    .marker-cluster div {
        background-color: #7171A3;
        color: white;
        font-weight: bold;
        border-radius: 50%;
    }
    </style>
</head>

<body>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
    var map = L.map('map').setView([48.1700, -2.7500], 8);

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

    function ajouterMarkerVendeur(vendeur) {
        var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || '';
        var nom = vendeur.nom || vendeur.denomination || vendeur.v_denomination || 'Vendeur';

        if (!adresse || adresse.trim() === '') {
            return;
        }

        var adresseEncodee = encodeURIComponent(adresse);
        var urlNominatim = 'https://nominatim.openstreetmap.org/search?q=' + adresseEncodee + '&format=json&limit=1';

        fetch(urlNominatim)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    var lat = data[0].lat;
                    var lon = data[0].lon;
                    var newMarker = L.marker([lat, lon], {
                        icon: iconVendeur
                    });

                    // Créer le contenu HTML de la popup
                    var popupContent = '<div class="vendor-popup">' +
                        '<h3>' + nom.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3>' +
                        '<p class="address-label">Adresse :</p>' +
                        '<p>' + adresse.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
                        '<button class="btn-voir-produits" data-vendeur="' + nom.replace(/"/g, '&quot;') +
                        '">Voir les produits</button>' +
                        '</div>';

                    newMarker.bindPopup(popupContent);

                    // Ajouter l'event listener après l'ouverture de la popup
                    newMarker.on('popupopen', function() {
                        var btn = document.querySelector('.leaflet-popup-content .btn-voir-produits');
                        if (btn) {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var nomVendeur = this.getAttribute('data-vendeur');
                                // Chercher le champ vendeur dans le formulaire parent
                                var searchVendeurInput = document.getElementById('searchVendeur');
                                if (searchVendeurInput) {
                                    searchVendeurInput.value = nomVendeur;
                                    // Soumettre le formulaire de filtrage
                                    var filterForm = document.getElementById('filterForm');
                                    if (filterForm) {
                                        filterForm.submit();
                                    }
                                }
                            });
                        }
                    });

                    markers.addLayer(newMarker);
                } else {
                    console.warn('Aucun résultat de géocodage pour:', adresse);
                }
            })
            .catch(error => console.error('Erreur de géocodage pour ' + adresse + ':', error));
    }

    // if (Array.isArray(adressesVendeurs)) {
    //     adressesVendeurs.forEach(function(vendeur) {
    //         if (vendeur && typeof vendeur === 'object') {
    //             ajouterMarkerVendeur(vendeur);
    //         }
    //     });
    // }

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
        }
    ];

    pointsBretagne.forEach(function(point) {
        var marker = L.marker([point.lat, point.lon], {
            icon: iconVendeur
        });
        marker.bindPopup('<b>' + point.nom + '</b>');
        markers.addLayer(marker);
    });
    </script>
</body>

</html>