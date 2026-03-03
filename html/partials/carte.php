<?php 

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];

$adresseDesVendeurs = getAdresseVendeur($connexionBaseDeDonnees, getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs));;


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js" />
</head>

<style>
#map {
    height: 150px;
    width: 100%;
    border: 1px solid #ccc;
    margin-top: 10px;
}

.leaflet-bottom {
    display: none;
}
</style>

<h4>Carte des vendeurs</h4>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.js"></script>

<script>
var map = L.map('map', {
    fullscreenControl: true
}).setView([48.733333, -3.466667], 13);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

// Créer le groupe de clusters
var markers = L.markerClusterGroup();
map.addLayer(markers);

var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

console.log('Adresses:', adressesVendeurs);

function ajouterMarkerVendeur(adresse, nom = '') {
    var adresseEncodee = encodeURIComponent(adresse);
    var urlNominatim = 'https://nominatim.openstreetmap.org/search?q=' + adresseEncodee + '&format=json&limit=1';

    console.log('URL générée:', urlNominatim);

    fetch(urlNominatim)
        .then(response => response.json())
        .then(data => {
            console.log('Résultat géocodage pour ' + adresse + ':', data);
            if (data && data.length > 0) {
                var lat = data[0].lat;
                var lon = data[0].lon;
                var newMarker = L.marker([lat, lon]);
                newMarker.bindPopup("<b>" + (nom || 'Vendeur') + "</b><br>" + adresse);
                markers.addLayer(newMarker);
            } else {
                console.warn('Aucun résultat de géocodage pour:', adresse);
            }
        })
        .catch(error => console.error('Erreur de géocodage pour ' + adresse + ':', error));
}

var markerAlizon = L.marker([48.75770187, -3.45408821]);
markerAlizon.bindPopup("<b>Alizon</b><br>La meilleur equipe de dev !");

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

</html>