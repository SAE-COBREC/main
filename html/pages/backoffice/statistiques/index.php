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

    //on crée un tableau pour les catégories ou il y a des commandes pour le selecte avec le filtre
    $categories = array();
    
    // Nouveau tableau pour la liste des articles (Graphique 3)
    $listeArticles = array();

    foreach ($commandes as $articleCommande){                          //pour chaque article de toutes les commandes
        if (!empty($articleCommande['date'])) {                        //si la date n'est pas vide
            $date = new DateTime($articleCommande['date']);            //cree un objet date avec la date courante
            if (!in_array($date->format('Y'), $anneeAvecVente))        //regarde si l'année n'est pas déjà dans le tableau
                array_push($anneeAvecVente, $date->format('Y'));       //on l'ajoute au tableau
        }
        if (!in_array($articleCommande['nom_categorie'], $categories))  { //regarde si la catégorie n'est pas déjà dans le tableau
            array_push($categories, $articleCommande['nom_categorie']);   //on l'ajoute au tableau
        }
        // On récupère les noms d'articles pour le nouveau filtre
        if (!in_array($articleCommande['p_nom'], $listeArticles)) {
            array_push($listeArticles, $articleCommande['p_nom']);
        }
    }
    sort($listeArticles);
} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>Alizon - Commandes Vendeur</title>
    <link rel="stylesheet" href="/styles/Statistique/statistique.css" />
    <script src="/js/chart.js"></script>
</head>
<body>
    <div class="app">
        <?php include __DIR__ . '/../../../partials/aside.html'; ?>
        
        <main class="main">
            <header class="header">
                <h1>Statistique</h1>
             </header>
                <section class="graphique-filtre">
                    <div id="filre">
                        <div id="divModeAffichage">
                            <label>Mode d'affichage</label>
                            <select id="modeAffichage">
                                <option value="annee">Année</option>
                                <option value="periode">Période</option>
                            </select>
                        </div>

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
                            
                        <div id="MontantVolume">
                            <label>Montant / volume</label>
                            <select id="selectType">
                                <option value="montant">Montant en €</option>
                                <option value="nbCommande">nombre de commandes</option>
                                <option value="nbArticle">nombre d'articles commandés</option>
                            </select>
                        </div>

                        <div id="divCategorie">
                            <label>Catégorie</label>
                            <select id="categorie">
                                <option value="toutes">Toutes</option>
                                <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo $categorie ?>"><?php echo $categorie?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <canvas id="graphiqueVentes"></canvas>
                    </div>

                    <div style="display: block;" id="divGraphiqueQuoiVendu">
                        <canvas id="graphiqueQuoiVendu"></canvas>
                    </div>

                    <div id="divSelectArticle">
                        <label>Article à suivre</label>
                        <select id="articleEvolution">
                            <?php foreach ($listeArticles as $art): ?>
                                <option value="<?php echo htmlspecialchars($art) ?>"><?php echo htmlspecialchars($art) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div>
                            <canvas id="graphiqueEvolutionArticle"></canvas>
                        </div>
                    </div>
                </section>
        </main>
    </div>
    <script>
        const canva1 = document.getElementById('graphiqueVentes').getContext('2d');
        const canva2 = document.getElementById('graphiqueQuoiVendu').getContext('2d');
        const canva3 = document.getElementById('graphiqueEvolutionArticle').getContext('2d');

        const selectModeAffichage = document.getElementById('modeAffichage');
        const groupeParAnnee = document.getElementById('groupeParAnnee');
        const groupeParPeriode = document.getElementById('groupeParPeriode');

        selectModeAffichage.addEventListener('change', function(){
            if (this.value === "annee"){
                groupeParAnnee.style.display = 'block';
                groupeParPeriode.style.display = 'none';
                document.getElementById('divSelectArticle').style.display = 'block';
            }else{
                groupeParAnnee.style.display = 'none';
                groupeParPeriode.style.display = 'block';
                document.getElementById('divSelectArticle').style.display = 'none';
            }
            chargerLesStats();
        });

        const selectType = document.getElementById('selectType');
        selectType.addEventListener('change', function(){
            if (this.value === "nbCommande"){
                document.getElementById('divGraphiqueQuoiVendu').style.display = 'none';
                document.getElementById('divSelectArticle').style.display = 'none';
            }else{
                document.getElementById('divGraphiqueQuoiVendu').style.display = 'block';
                document.getElementById('divSelectArticle').style.display = 'block';
            }
            chargerLesStats();
        });

        const graph1 = new Chart(canva1, {
            type: 'bar',
            data: {
                labels: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                datasets: [{
                    label: 'gains total en €',
                    data: [],
                    backgroundColor: 'rgba(230, 169, 110, 1)',
                    borderColor: 'rgba(236, 142, 55, 0.9)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        const graph2 = new Chart(canva2, {
            type: 'bar',
            data:{
                labels: [],
                datasets: [{
                    label: "nombres d'articles vendus",
                    data: [],
                    backgroundColor: 'rgba(230, 169, 110, 1)',
                    borderColor: 'rgba(236, 142, 55, 0.9)',
                    hoverOffset: 4
                }]
            },
            options: { scales: { y: { beginAtZero: true, grace: '10%' } } }
        });

        // Initialisation du Graphique 3 (Évolution)
        const graph3 = new Chart(canva3, {
            type: 'bar',
            data: {
                labels: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                datasets: [{
                    label: 'Évolution de l\'article',
                    data: [],
                    backgroundColor: 'rgba(230, 169, 110, 1)',
                    borderColor: 'rgba(230, 169, 110, 1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { scales: { y: { beginAtZero: true, grace: '10%' } } }
        });


        function chargerLesStats() {
            const modeAffichage = document.getElementById('modeAffichage').value; //mode affiche corresponde a année ou période
            const type = document.getElementById('selectType').value;             //le type est si on veut en volume ou en nb de commande ou prix
            const categorie = document.getElementById('categorie').value;         //la catégorie choisi
            const article = document.getElementById('articleEvolution').value;

            // Paramètres communs pour les deux requêtes
            let queryParams = `type=${type}&modeAffichage=${modeAffichage}`;

            if (modeAffichage === "annee"){                           //si le mode est annnee
                const annee = document.getElementById("annee").value; //on récup l'année
                queryParams += `&annee=${annee}`;                             //on l'ajoute a l'url les données
            } else {                                                  //sinon le mode est période
                const debut = document.getElementById("dateDebut").value; // on récup le début
                const fin = document.getElementById("dateFin").value;     // on récup la fin
                if (!debut || !fin) return;                               //on regarde si les 2 ont été saisient
                queryParams += `&debut=${debut}&fin=${fin}`;                      //on ajoute a l'url les données
            }

            // 1. Appel pour les graphiques 1 et 2 (Inchangé)
            fetch(`donneToutesStats.php?${queryParams}&categorie=${categorie}`)
                .then(reponse => reponse.json())
                .then(donnees => {
                    graph1.data.datasets[0].data = donnees.graph1;
                    graph1.update();
            
                    graph2.data.labels = donnees.graph2.labels;
                    graph2.data.datasets[0].data = donnees.graph2.data;
                    graph2.update();
                })
                .catch(err => console.error("Erreur AJAX Graph 1&2 :", err));

            // 2. Appel spécifique pour le graphique 3
            fetch(`statsGraph3.php?${queryParams}&article=${encodeURIComponent(article)}`)
                .then(reponse => reponse.json())
                .then(donnees => {
                    graph3.data.datasets[0].label = `Ventes : ${article}`;
                    graph3.data.datasets[0].data = donnees.graph3;
                    graph3.update();
                })
                .catch(err => console.error("Erreur AJAX Graph 3 :", err));
        }

        //on écoute les changements
        document.getElementById('annee').addEventListener('change', chargerLesStats);
        document.getElementById('selectType').addEventListener('change', chargerLesStats);
        document.getElementById('dateDebut').addEventListener('change', chargerLesStats);
        document.getElementById('dateFin').addEventListener('change', chargerLesStats);
        document.getElementById('categorie').addEventListener('change', chargerLesStats);
        document.getElementById('articleEvolution').addEventListener('change', chargerLesStats);

        //on lance une première fois au chargement
        window.onload = chargerLesStats;
    </script>
</body>
</html>