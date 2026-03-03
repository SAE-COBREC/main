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

    <!-- FullScreen -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.css" />

    <style>
    .leaflet-bottom {
        display: none;
    }
    </style>
</head>

<body>
    <h4>Carte des vendeurs</h4>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
    var map = L.map('map', {
        fullscreenControl: true
    }).setView([48.733333, -3.466667], 10);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var iconVendeur = L.icon({
        iconUrl: '/img/favicon.svg',
        iconSize: [20, 20],
        iconAnchor: [20, 20],
        popupAnchor: [-10, -20]
    });

    var markers = L.markerClusterGroup();
    map.addLayer(markers);

    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

    function ajouterMarkerVendeur(adresse, nom = '') {
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
                    newMarker.bindPopup("<b>" + (nom || 'Vendeur') + "</b><br>" + adresse);

                    newMarker.on('click', function() {
                        var searchInput = document.getElementById(
                            'searchVendeur'); // Champ de recherche vendeur
                        var filterForm = document.getElementById('filterForm'); // Formulaire de filtres

                        // Si le formulaire et le champ existent, et qu'on a un nom de vendeur
                        if (searchInput && filterForm && nom) {
                            searchInput.value = nom; // Remplit le champ avec le nom du vendeur
                            filterForm
                                .submit(); // Soumet le formulaire pour actualiser la liste des produits
                        }
                    });

                    markers.addLayer(newMarker);
                } else {
                    console.warn('Aucun résultat de géocodage pour:', adresse);
                }
            })
            .catch(error => console.error('Erreur de géocodage pour ' + adresse + ':', error));
    }

    if (Array.isArray(adressesVendeurs)) {
        adressesVendeurs.forEach(function(vendeur) {
            if (vendeur && typeof vendeur === 'object') {
                var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || vendeur;
                if (adresse && typeof adresse === 'string' && adresse.trim() !== '') {
                    ajouterMarkerVendeur(adresse, vendeur.nom || vendeur.denomination || '');
                }
            } else if (typeof vendeur === 'string' && vendeur.trim() !== '') {
                ajouterMarkerVendeur(vendeur);
            }
        });
    }
    </script>
</body>

</html>