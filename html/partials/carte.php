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

    .vendor-popup {
        font-family: Arial, sans-serif;
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
    }

    .vendor-popup button:hover {
        background-color: #FFFFFF;
        color: #7171A3;
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