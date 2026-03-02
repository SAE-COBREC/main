<?php 
//démarre la session utilisateur
session_start();

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];

$IdDesVendeurs = getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs);

$adresseDesVendeurs = getAdresseVendeur($connexionBaseDeDonnees, $IdDesVendeurs);



echo "<pre>";
print_r($adresseDesVendeurs);
echo "</pre>";


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.css" />
</head>

<style>
#map {
    height: 150px;
    width: 100%;
    border: 1px solid #ccc;
    margin-top: 10px;
}
</style>

<h4>Carte des vendeurs</h4>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.js"></script>

<script>
var map = L.map('map', {
    fullscreenControl: true
}).setView([48.733333, -3.466667], 13);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

// Données des vendeurs transmises depuis index.php
var vendeursData = <?= json_encode($vendeursUniques) ?>;

// Afficher un marqueur pour chaque vendeur
var marker = L.marker([48.75770187, -3.45408821]).addTo(map);
marker.bindPopup("<b>Alizon</b><br>La meilleur equipe de dev !").openPopup();

// Données additionnelles des vendeurs (à obtenir de la base de données)
console.log("Vendeurs filtrés:", vendeursData);
</script>

</html>