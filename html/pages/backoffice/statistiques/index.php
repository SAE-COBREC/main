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
    $commandes = recupInfoPourStatsGeneral($pdo, $vendeur_id);

    //on crée un tableau pour les années ou il y a des commandes pour le selecte avec le filtre
    $anneeAvecVente = array();
    foreach ($commandes as $articleCommande){                          //pour chaque article de toutes les commandes
        if (!empty($articleCommande['date'])) {                        //si la date n'est pas vide
            $date = new DateTime($articleCommande['date']);            //cree un objet date avec la date courante
            if (!in_array($date->format('Y'), $anneeAvecVente))        //regarde si l'année est déjà dans le tableau
                array_push($anneeAvecVente, $date->format('Y')); 
        }
    }
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
                    <label>Mode d'affichage</label>
                        <select name="modeAffichage" id="modeAffichage">
                            <option value="annee">Année</option>
                            <option value="periode">Période</option>
                        </select>

                    <div id="groupeParAnnee"> 
                    <label>Année</label>
                        <select name="annee" id="annee">
                            <?php foreach($anneeAvecVente as $annee) :?>
                            <option value="<?php echo $annee ?>"><?php echo $annee?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="groupeParPeriode" style="display: none;">
                        <label>Début</label>
                        <input type="date" id="dateDebut"></input>
                        <label>Fin</label>
                        <input type="date" id="dateFin"></input>
                    </div>

                    <label>Volume / montant</label>
                    <select name="type" id="type">
                        <option value="montant">Montant en €</option>
                        <option value="nbCommande">nombre de commandes</option>
                        <option value="nbArticle">nombre d'articles commandés</option>
                    </select>

                <div>
                    <canvas id="graphiqueVentes"></canvas>
                </div>

                <div>
                    <canvas id="graphiqueQuoiVendu"></canvas>
                </div>

            </header>
        </main>
    </div>
    <script>
        const ctx1 = document.getElementById('graphiqueVentes').getContext('2d');
        const selectModeAffichage = document.getElementById('modeAffichage');
        const groupeParAnnee = document.getElementById('groupeParAnnee');
        const groupeParPeriode = document.getElementById('groupeParPeriode');

        selectModeAffichage.addEventListener('change', function(){
            if (this.value === "annee"){
                groupeParAnnee.style.display = 'block';
                groupeParPeriode.style.display = 'none';
            }else{
                groupeParAnnee.style.display = 'none';
                groupeParPeriode.style.display = 'block';
            }
            chargerLesStats();
        });

        const graph1 = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?php echo "['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],"?>
                datasets: [{
                    label: 2025,
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


        function chargerLesStats() {
            const modeAffichage = document.getElementById('modeAffichage').value; //mode affiche corresponde a année ou période
            const type = document.getElementById('type').value;                   //le type est si on veut en volume ou en nb de commande ou prix
            let url = `donneToutesStats.php?type=${type}&modeAffichage=${modeAffichage}`;

            if (modeAffichage === "annee"){                           //si le mode est annnee
                const annee = document.getElementById("annee").value; //on récup l'année
                url += `&annee=${annee}`;                             //on l'ajoute a l'url les données
            } else {                                                  //sinon le mode est période
                const debut = document.getElementById("dateDebut").value; // on récup le début
                const fin = document.getElementById("dateFin").value;     // on récup la fin
                if (!debut || !fin) return;                               //on regarde si les 2 ont été saisient
                url += `&debut=${debut}&fin=${fin}`;                      //on ajoute a l'url les données
            }


            fetch(url)
                .then(reponse => reponse.json())
                .then(donnees => {
                    graph1.data.datasets[0].data = donnees;

                    graph1.update();
                })
                .catch(err => console.error("Erreur AJAX :", err));
        }

        //on écoute les changements
        document.getElementById('annee').addEventListener('change', chargerLesStats);
        document.getElementById('type').addEventListener('change', chargerLesStats);
        document.getElementById('dateDebut').addEventListener('change', chargerLesStats);
        document.getElementById('dateFin').addEventListener('change', chargerLesStats);

        //on lance une première fois au chargement
        window.onload = chargerLesStats;
    </script>
</body>
</html>