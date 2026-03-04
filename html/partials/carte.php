<?php 
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//charge le fichier de connexion à la base de données
require_once __DIR__ . '/../selectBDD.php';
//charge le fichier contenant toutes les fonctions personnalisées
require_once __DIR__ . '/../pages/fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;
//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// TRAITEMENT AJAX : SAUVEGARDE DES COORDONNÉES
// ============================================

//vérifie si c'est une requête de sauvegarde de coordonnées géocodées
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_coords') {
    //définit le type de réponse en JSON
    header('Content-Type: application/json; charset=utf-8');
    //récupère l'identifiant de l'adresse à mettre à jour
    $idAdresse = isset($_POST['id_adresse']) ? (int) $_POST['id_adresse'] : 0;
    //récupère la latitude géocodée
    $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
    //récupère la longitude géocodée
    $lon = isset($_POST['lon']) ? (float) $_POST['lon'] : null;

    //vérifie que les paramètres reçus sont valides
    if ($idAdresse > 0 && $lat !== null && $lon !== null) {
        try {
            //prépare la requête de mise à jour des coordonnées dans la base
            $stmt = $connexionBaseDeDonnees->prepare(
                "UPDATE cobrec1._adresse SET latitude = :lat, longitude = :lon WHERE id_adresse = :id"
            );
            //exécute la mise à jour avec les nouvelles coordonnées
            $stmt->execute([':lat' => $lat, ':lon' => $lon, ':id' => $idAdresse]);
            //renvoie un succès en JSON
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            //renvoie l'erreur en JSON si la mise à jour échoue
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        //renvoie une erreur si les paramètres sont invalides ou manquants
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    }
    //arrête le script après traitement de la requête AJAX
    exit;
}

// ============================================
// CHARGEMENT DES DONNÉES VENDEURS
// ============================================

//récupère la liste des vendeurs sauvegardée en session
$listesVendeurs = $_SESSION['listesVendeurs'] ?? [];

//récupère les adresses des vendeurs depuis la base de données
$adresseDesVendeurs = getAdresseVendeur($connexionBaseDeDonnees, getIdVendeurParliste($connexionBaseDeDonnees, $listesVendeurs));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <!-- charge la feuille de style de la bibliothèque Leaflet pour la carte -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- charge les styles du plugin de regroupement de marqueurs -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <!-- charge la feuille de style personnalisée de la carte -->
    <link rel="stylesheet" href="/styles/Carte/style.css">

</head>

<body>
    <!-- conteneur HTML dans lequel la carte Leaflet sera rendue -->
    <div id="map"></div>

    <!-- charge la bibliothèque Leaflet pour l'affichage de la carte -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- charge le plugin de regroupement de marqueurs -->
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
    //initialise la carte Leaflet centrée sur la Bretagne avec un niveau de zoom 8
    var map = L.map('map').setView([48.2500, -2.7500], 8);

    //définit les différentes couches de fond de carte disponibles
    var baselayers = {
        //couche OpenStreetMap France
        OSM: L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap France'
        }),
        //couche topographique ESRI
        ESRI: L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI'
            }),
        //couche CartoDB claire
        CartoDB: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
            attribution: '&copy; CartoDB'
        }),
        //couche satellite ESRI
        Satellite: L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; ESRI World Imagery'
            })
    };
    //ajoute la couche ESRI par défaut à la carte
    baselayers.ESRI.addTo(map);

    //ajoute une barre d'échelle en bas de la carte
    L.control.scale({
        imperial: false
    }).addTo(map);

    //ajoute le sélecteur de couche en haut à droite de la carte
    L.control.layers(baselayers, null, {
        collapsed: false,
        position: 'topright'
    }).addTo(map);

    //définit l'icône personnalisée pour les marqueurs vendeurs
    var iconVendeur = L.icon({
        iconUrl: '/img/png/badge-bretagne.png',
        iconSize: [33, 33],
        iconAnchor: [20, 20],
        popupAnchor: [-10, -20]
    });

    //crée le groupe de regroupement de marqueurs
    var markers = L.markerClusterGroup();
    //ajoute le groupe de marqueurs à la carte
    map.addLayer(markers);

    //récupère les adresses des vendeurs transmises depuis PHP
    var adressesVendeurs = <?php echo json_encode($adresseDesVendeurs); ?>;

    //fonction pour placer un marqueur vendeur sur la carte avec une popup
    function placerMarker(lat, lon, nom, adresse) {
        //crée un nouveau marqueur avec l'icône vendeur aux coordonnées données
        var newMarker = L.marker([lat, lon], {
            icon: iconVendeur
        });

        //construit le contenu HTML de la popup du marqueur
        var popupContent = '<div class="vendor-popup">' +
            '<h3>' + nom.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3>' +
            '<p class="address-label">Adresse :</p>' +
            '<p>' + adresse.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
            '<button class="btn-voir-produits" data-vendeur="' + nom.replace(/"/g, '&quot;') +
            '">Voir les produits</button>' +
            '</div>';

        //attache la popup au marqueur
        newMarker.bindPopup(popupContent);

        //écoute l'ouverture de la popup pour attacher l'événement sur le bouton
        newMarker.on('popupopen', function() {
            //récupère le bouton "Voir les produits" dans la popup
            var btn = document.querySelector('.leaflet-popup-content .btn-voir-produits');
            if (btn) {
                //écoute le clic sur le bouton de la popup
                btn.addEventListener('click', function(e) {
                    //empêche le comportement par défaut du bouton
                    e.preventDefault();
                    //récupère le nom du vendeur stocké dans l'attribut data
                    var nomVendeur = this.getAttribute('data-vendeur');
                    //récupère le champ de recherche par vendeur
                    var searchVendeurInput = document.getElementById('searchVendeur');
                    if (searchVendeurInput) {
                        //remplit le champ de recherche avec le nom du vendeur
                        searchVendeurInput.value = nomVendeur;
                        //récupère le formulaire de filtres
                        var filterForm = document.getElementById('filterForm');
                        if (filterForm) {
                            //soumet le formulaire pour filtrer les produits de ce vendeur
                            filterForm.submit();
                        }
                    }
                });
            }
        });

        //ajoute le marqueur au groupe de regroupement
        markers.addLayer(newMarker);
    }

    //fonction pour sauvegarder les coordonnées géocodées en base de données via AJAX
    function sauvegarderCoords(idAdresse, lat, lon) {
        //crée un objet pour envoyer les données au serveur
        var formData = new FormData();
        //ajoute l'action de sauvegarde
        formData.append('action', 'save_coords');
        //ajoute l'identifiant de l'adresse
        formData.append('id_adresse', idAdresse);
        //ajoute la latitude
        formData.append('lat', lat);
        //ajoute la longitude
        formData.append('lon', lon);
        //envoie la requête AJAX en POST vers la page courante
        fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .catch(function(err) {
                //affiche un avertissement en cas d'échec de la sauvegarde
                console.warn('Impossible de sauvegarder les coordonnées:', err);
            });
    }

    //fonction pour ajouter le marqueur d'un vendeur sur la carte
    function ajouterMarkerVendeur(vendeur) {
        //récupère l'adresse textuelle du vendeur selon le nom de champ disponible
        var adresse = vendeur.adresse || vendeur.p_adresse || vendeur.v_adresse || '';
        //récupère le nom du vendeur selon le nom de champ disponible
        var nom = vendeur.nom || vendeur.denomination || vendeur.v_denomination || 'Vendeur';
        //récupère l'identifiant de l'adresse pour la sauvegarde des coordonnées
        var idAdresse = vendeur.id_adresse || null;

        //ignore le vendeur si son adresse est vide
        if (!adresse || adresse.trim() === '') {
            return;
        }

        //vérifie si les coordonnées sont déjà enregistrées en base de données
        if (vendeur.latitude !== null && vendeur.latitude !== undefined &&
            vendeur.longitude !== null && vendeur.longitude !== undefined) {
            //place directement le marqueur avec les coordonnées sauvegardées
            placerMarker(vendeur.latitude, vendeur.longitude, nom, adresse);
            return;
        }

        //encode l'adresse pour l'utiliser dans l'URL de l'API de géocodage
        var adresseEncodee = encodeURIComponent(adresse);
        //construit l'URL de requête vers l'API Nominatim d'OpenStreetMap
        var urlNominatim = 'https://nominatim.openstreetmap.org/search?q=' + adresseEncodee + '&format=json&limit=1';

        //interroge l'API Nominatim pour obtenir les coordonnées de l'adresse
        fetch(urlNominatim)
            .then(function(response) {
                //convertit la réponse en JSON
                return response.json();
            })
            .then(function(data) {
                //vérifie que l'API a retourné au moins un résultat
                if (data && data.length > 0) {
                    //extrait la latitude depuis le résultat
                    var lat = parseFloat(data[0].lat);
                    //extrait la longitude depuis le résultat
                    var lon = parseFloat(data[0].lon);
                    //place le marqueur sur la carte avec les coordonnées obtenues
                    placerMarker(lat, lon, nom, adresse);
                    //sauvegarde les coordonnées en base pour éviter de recalculer
                    if (idAdresse) {
                        sauvegarderCoords(idAdresse, lat, lon);
                    }
                } else {
                    //affiche un avertissement si aucun résultat de géocodage n'est trouvé
                    console.warn('Aucun résultat de géocodage pour:', adresse);
                }
            })
            .catch(function(error) {
                //affiche une erreur en cas d'échec de la requête de géocodage
                console.error('Erreur de géocodage pour ' + adresse + ':', error);
            });
    }

    //vérifie que la liste des adresses est bien un tableau
    if (Array.isArray(adressesVendeurs)) {
        //parcourt chaque vendeur de la liste
        adressesVendeurs.forEach(function(vendeur) {
            //vérifie que l'entrée est un objet valide avant de placer le marqueur
            if (vendeur && typeof vendeur === 'object') {
                ajouterMarkerVendeur(vendeur);
            }
        });
    }

    //définit une liste de points fixes représentant les grandes villes de Bretagne
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

    //parcourt chaque point fixe de Bretagne pour l'ajouter sur la carte
    pointsBretagne.forEach(function(point) {
        //crée un marqueur standard à la position de la ville
        var marker = L.marker([point.lat, point.lon]);

        //attache un tooltip permanent affichant le nom de la ville
        marker.bindTooltip('<b>' + point.nom + '</b>', {
            permanent: true,
            direction: 'top',
            offset: [-15, -10]
        });

        //ajoute le marqueur de ville au groupe de marqueurs
        markers.addLayer(marker);
    });

        function ajouterPositionUtilisateur() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lon = position.coords.longitude;

                var iconUtilisateur = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                var markerUtilisateur = L.marker([lat, lon], { icon: iconUtilisateur });
                markerUtilisateur.bindPopup('<b>Vous etes ici</b>');
            }, function(error) {
                console.error('Erreur de géolocalisation:', error);
                alert('Impossible de récupérer votre position. Vérifiez les permissions de géolocalisation.');
            });
        } else {
            alert('La géolocalisation n\'est pas supportée par ce navigateur.');
        }
    }

    ajouterPositionUtilisateur();
    </script>
</body>

</html>