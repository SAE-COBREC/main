<?php 
session_start(); 

// Utilisation de require_once avec __DIR__ pour éviter l'erreur "PDO on null"
include '../../../selectBDD.php';
include __DIR__ . '../../../fonctions.php';

// Vérification de connexion
if(empty($_SESSION['vendeur_id'])){
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit();
}
$vendeur_id = $_SESSION['vendeur_id'];

// Nettoyage session et dossiers temporaires
$_SESSION['creerArticle'] = [];
$_SESSION['remise'] = [];
$_SESSION['promotion'] = [];
$fichiers = glob('create/temp_/*');
foreach ($fichiers as $value) {
    if(is_file($value)) unlink($value);
}

try {
    $query = "
    SELECT c.id_produit,
           c.quantite,
           p.p_nom,
           p.p_prix,
           p.p_nb_ventes,
           c.id_panier,
           pc.timestamp_commande AS date
    FROM cobrec1._panier_commande pc
    LEFT JOIN cobrec1._contient c ON pc.id_panier = c.id_panier
    LEFT JOIN cobrec1._produit p ON c.id_produit = p.id_produit
    WHERE p.id_vendeur = :id_vendeur";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id_vendeur' => $vendeur_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //on crée un tableau pour les années ou il y a des commandes pour le selecte avec le filtre
    $anneeAvecVente = array();

    //on créé un tableau de taille 12 rempli de 0
    $ventesParMois = array_fill(1, 12, 0);

    $anneeChoisi = 2025; // a compléter avec ajax
    $volumeOuMontant = "nbCommande";
    foreach ($commandes as $articleCommande){                          //pour chaque article de toutes les commandes
        if (!empty($articleCommande['date'])) {                        //si la date n'est pas vide
            $date = new DateTime($articleCommande['date']);            //cree un objet date avec la date courante
            if (!in_array($date->format('Y'), $anneeAvecVente))        //regarde si l'année est déjà dans le tableau
                array_push($anneeAvecVente, $date->format('Y'));       //on ajoute l'année dans les options du select
            
            $mois = $date->format('n'); //on récupère le numéro du mois pour push dans le bonne indice du tableau (janvier indice 1, février indice 2...)

            //------------------------------------DEMANDE SI ON DOIT METTRE LA TVA ET LES FRAIS DE PORTS DANS LES STATS ------------------------------------
            if ($date->format('Y') == $anneeChoisi){
                if ($volumeOuMontant == "montant"){
                    $totalArticleCommande = $articleCommande['p_prix'] * $articleCommande['quantite']; //on calcul le prix total des articles en fonction de la quantité
                    $ventesParMois[$mois] += $totalArticleCommande;                                    //on ajoute le total dans le mois
                } 
                else if ($volumeOuMontant == "nbArticle"){
                    $ventesParMois[$mois] += $articleCommande['quantite'];
                }
                else if ($volumeOuMontant == "nbCommande"){
                    $estDansDonnees = array();
                    if (!in_array($articleCommande['id_panier'], $estDansDonnees))
                        $ventesParMois[$mois] += 1;
                }
            }
        }
        
    }
    $donneesGraphique = array_values($ventesParMois); //on converti le tableau pour qu'il soit au bon format pour le graphique

} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>Alizon - Commandes Vendeur</title>
    <link rel="stylesheet" href="/styles/CommandeVendeur/commande.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app">
        <?php include __DIR__ . '/../../../partials/aside.html'; ?>
        
        <main class="main">
            <header class="header">
                <h1>Statistique</h1>
                <form id="filtreStatistique" method="POST" action="/pages/backoffice/statistiques/index.php">
                    <label>Année</label>
                    <select name="annee" id="annee">
                        <?php foreach($anneeAvecVente as $annee) :?>
                        <option value="<?php echo $annee ?>"><?php echo $annee?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Volume / montant</label>
                    <select name="volumeOuMontant" id="volumeOuMontant">
                        <option value="montant">Montant en €</option>
                        <option value="nbCommande">nombre de commandes</option>
                        <option value="nbArticle">nombre d'articles commandés</option>
                    </select>
                <div>
                    <canvas id="graphiqueVentes"></canvas>
                </div>
            </header>
        </main>
    </div>
    <script>
        const ctx = document.getElementById('graphiqueVentes').getContext('2d');

        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo "['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],"?>
                datasets: [{
                    label: 'Ventes 2026 (€)',
                    data: <?php echo json_encode($donneesGraphique); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>