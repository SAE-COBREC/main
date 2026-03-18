<?php
// ============================================
// CONFIGURATION ET INITIALISATION
// ============================================

//démarre la session utilisateur pour accéder aux données de connexion
session_start();

//charge le fichier de connexion à la base de données
include '../../selectBDD.php';

//charge le fichier contenant toutes les fonctions personnalisées
include '../fonctions.php';

//crée la connexion à la base de données
$connexionBaseDeDonnees = $pdo;

//définit le schéma de base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// ============================================
// VÉRIFICATION DE LA SESSION CLIENT
// ============================================

//redirige vers la page de connexion si le client n'est pas connecté
if (!isset($_SESSION['idClient'])) {
    header("Location: /pages/connexionClient/index.php");
    exit;
}

//récupère et sécurise l'identifiant du client depuis la session
$identifiantClientConnecte = (int) $_SESSION['idClient'];

// ============================================
// PRÉ-CHARGEMENT DES ANNÉES ET CATÉGORIES
// ============================================

try {
    //prépare la requête pour récupérer les années et catégories disponibles pour ce client
    //triées par année décroissante pour afficher la plus récente en premier
    $requeteSQLMetaDonnees = "
        SELECT DISTINCT
            EXTRACT(YEAR FROM pc.timestamp_commande)::int  AS annee,
            cp.nom_categorie
        FROM cobrec1._panier_commande pc
        LEFT JOIN cobrec1._contient c          ON pc.id_panier    = c.id_panier
        LEFT JOIN cobrec1._produit p           ON c.id_produit    = p.id_produit
        LEFT JOIN cobrec1._fait_partie_de fpd  ON p.id_produit    = fpd.id_produit
        LEFT JOIN cobrec1._categorie_produit cp ON cp.id_categorie = fpd.id_categorie
        WHERE pc.id_client = :id_client
          AND pc.timestamp_commande IS NOT NULL
        ORDER BY annee DESC
    ";

    //exécute la requête avec l'identifiant du client
    $requetePrepareeMetaDonnees = $pdo->prepare($requeteSQLMetaDonnees);
    $requetePrepareeMetaDonnees->execute(['id_client' => $identifiantClientConnecte]);

    //récupère tous les résultats
    $lignesMetaDonnees = $requetePrepareeMetaDonnees->fetchAll(PDO::FETCH_ASSOC);

    //initialise les tableaux des années et catégories disponibles
    $listeAnneesDisponibles     = [];
    $listeCategoriesDisponibles = [];

    //parcourt les résultats pour extraire les années et catégories uniques
    foreach ($lignesMetaDonnees as $ligneMetaDonnee) {
        //ajoute l'année si elle n'est pas déjà présente dans la liste
        if ($ligneMetaDonnee['annee'] && !in_array($ligneMetaDonnee['annee'], $listeAnneesDisponibles))
            $listeAnneesDisponibles[] = $ligneMetaDonnee['annee'];
        //ajoute la catégorie si elle n'est pas déjà présente dans la liste
        if ($ligneMetaDonnee['nom_categorie'] && !in_array($ligneMetaDonnee['nom_categorie'], $listeCategoriesDisponibles))
            $listeCategoriesDisponibles[] = $ligneMetaDonnee['nom_categorie'];
    }

    //garantit qu'au moins l'année courante est disponible si aucune commande n'existe
    if (empty($listeAnneesDisponibles)) $listeAnneesDisponibles[] = date('Y');

} catch (PDOException $e) {
    //arrête l'exécution en cas d'erreur de base de données
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}

//détermine l'année par défaut à sélectionner dans le filtre (la plus récente)
$anneeSelectionneeParDefaut = $listeAnneesDisponibles[0] ?? date('Y');

// Récupération du thème de daltonisme depuis la session
$current_theme = isset($_SESSION['colorblind_mode']) ? $_SESSION['colorblind_mode'] : 'default';
?>

<!doctype html>
<html lang="fr"
    <?php echo ($current_theme !== 'default') ? 'data-theme="' . htmlspecialchars($current_theme) . '"' : ''; ?>>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mes statistiques de commandes – Alizon</title>
    <link rel="icon" type="image/svg+xml" href="/img/favicon.svg" />
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css" />
    <link rel="stylesheet" href="/styles/Footer/stylesFooter.css" />
    <link rel="stylesheet" href="/styles/ProfilClient/statsCommandes.css" />
    <script src="/js/chart.js"></script>
    <script src="../../js/accessibility.js"></script>
</head>

<body>

    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div id="loadingOverlay">
        <div></div>
    </div>

    <main>
        <div>
            <!-- Lien retour -->
            <a href="/pages/ProfilClient/index.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Retour au profil
            </a>

            <!-- Titre -->
            <div>
                <h1>Mes statistiques de commandes</h1>
                <p>Visualisez l'historique et les tendances de vos achats.</p>
            </div>

            <!-- ── Filtres ── -->
            <div>

                <div>
                    <label for="modeAffichage">Mode</label>
                    <select id="modeAffichage">
                        <option value="annee">Année</option>
                        <option value="periode">Période</option>
                    </select>
                </div>

                <div id="groupeAnnee">
                    <label for="selectAnnee">Année</label>
                    <select id="selectAnnee">
                        <?php foreach ($listeAnneesDisponibles as $annee): ?>
                        <option value="<?= $annee ?>"><?= $annee ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="groupePeriode" style="display:none;">
                    <label>Période</label>
                    <div>
                        <input type="date" id="dateDebut" />
                        <input type="date" id="dateFin" />
                    </div>
                </div>

                <div>
                    <label for="selectType">Afficher par</label>
                    <select id="selectType">
                        <option value="montant">Montant (€)</option>
                        <option value="nbArticle">Nombre d'articles</option>
                        <option value="nbCommande">Nombre de commandes</option>
                    </select>
                </div>

                <div>
                    <label for="selectCategorie">Catégorie</label>
                    <select id="selectCategorie">
                        <option value="toutes">Toutes</option>
                        <?php foreach ($listeCategoriesDisponibles as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <!-- ── KPI Cards ── -->
            <div>
                <div>
                    <span>Total dépensé</span>
                    <span id="kpiDepense">–</span>
                    <span>sur la période sélectionnée</span>
                </div>
                <div>
                    <span>Commandes passées</span>
                    <span id="kpiCommandes">–</span>
                    <span>commandes</span>
                </div>
                <div>
                    <span>Articles achetés</span>
                    <span id="kpiArticles">–</span>
                    <span>unités</span>
                </div>
                <div>
                    <span>Panier moyen</span>
                    <span id="kpiMoyenne">–</span>
                    <span id="kpiTopProduit">–</span>
                </div>
            </div>

            <!-- ── Graphiques ── -->
            <div>

                <!-- Graph 1 : évolution mensuelle -->
                <div>
                    <h2 id="titreGraph1">Montant mensuel (€)</h2>
                    <canvas id="graphique1"></canvas>
                </div>

                <!-- Graph 2 : top produits -->
                <div id="carteGraph2">
                    <h2 id="titreGraph2">Top 10 produits</h2>
                    <canvas id="graphique2"></canvas>
                </div>

                <!-- Graph 3 : répartition par catégorie -->
                <div id="carteGraph3">
                    <h2>Répartition par catégorie</h2>
                    <canvas id="graphique3"></canvas>
                </div>

            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../../partials/footer.html'; ?>
    <?php include __DIR__ . '/../../partials/toast.html'; ?>

    <script>
    // ============================================
    // INITIALISATION DES GRAPHIQUES CHART.JS
    // ============================================

    //palette de couleurs utilisée pour les graphiques en barres et en donut
    const COULEURS_PALETTE = [
        'rgba(205, 127, 50, 0.85)',
        'rgba(113, 113, 163, 0.85)',
        'rgba(40, 167, 69, 0.85)',
        'rgba(230, 169, 110, 0.85)',
        'rgba(220, 53, 69, 0.85)',
        'rgba(23, 162, 184, 0.85)',
        'rgba(255, 193, 7, 0.85)',
        'rgba(111, 66, 193, 0.85)',
        'rgba(253, 126, 20, 0.85)',
        'rgba(32, 201, 151, 0.85)',
        'rgba(102, 16, 242, 0.85)',
        'rgba(214, 51, 132, 0.85)',
    ];

    //tableau des libellés des mois en français pour l'axe du graphique mensuel
    const MOIS_LABELS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];

    //récupère les contextes de dessin des trois canvas
    const ctx1 = document.getElementById('graphique1').getContext('2d');
    const ctx2 = document.getElementById('graphique2').getContext('2d');
    const ctx3 = document.getElementById('graphique3').getContext('2d');

    //initialise le graphique 1 : évolution mensuelle en barres verticales
    const graph1 = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: MOIS_LABELS,
            datasets: [{
                label: 'Montant (€)',
                data: [],
                backgroundColor: '#7171A3',
                borderColor: '#7171a383',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grace: '5%'
                }
            }
        }
    });

    //initialise le graphique 2 : top produits en barres horizontales
    const graph2 = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Top produits',
                data: [],
                backgroundColor: COULEURS_PALETTE,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grace: '5%'
                }
            }
        }
    });

    //initialise le graphique 3 : répartition par catégorie en donut
    const graph3 = new Chart(ctx3, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: COULEURS_PALETTE,
                borderWidth: 2,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 14,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // GESTION DES FILTRES ET INTERACTIONS
    // ============================================

    //récupère les éléments du DOM pour les filtres
    const selectMode = document.getElementById('modeAffichage');
    const groupeAnnee = document.getElementById('groupeAnnee');
    const groupePeriode = document.getElementById('groupePeriode');
    const selectType = document.getElementById('selectType');
    const carteGraph2 = document.getElementById('carteGraph2');
    const carteGraph3 = document.getElementById('carteGraph3');

    //écoute le changement de mode d'affichage (année / période)
    selectMode.addEventListener('change', function() {
        //affiche le groupe année ou le groupe période selon le mode choisi
        const estAnnee = this.value === 'annee';
        groupeAnnee.style.display = estAnnee ? '' : 'none';
        groupePeriode.style.display = estAnnee ? 'none' : '';
        //recharge les statistiques avec le nouveau mode
        chargerStats();
    });

    //écoute le changement du type d'affichage (montant / nb articles / nb commandes)
    selectType.addEventListener('change', function() {
        //masque les graphiques produits et catégorie en mode nombre de commandes
        const cacheGraphsProduits = this.value === 'nbCommande';
        carteGraph2.style.display = cacheGraphsProduits ? 'none' : '';
        carteGraph3.style.display = cacheGraphsProduits ? 'none' : '';
        //recharge les statistiques avec le nouveau type
        chargerStats();
    });

    //écoute les changements sur les autres filtres (année, dates, catégorie)
    ['selectAnnee', 'dateDebut', 'dateFin', 'selectCategorie'].forEach(id => {
        document.getElementById(id).addEventListener('change', chargerStats);
    });

    // ============================================
    // CHARGEMENT DES DONNÉES VIA APPEL AJAX
    // ============================================

    //fonction principale de chargement des statistiques depuis le serveur
    function chargerStats() {
        //récupère les valeurs actuelles des filtres
        const modeAffichage = selectMode.value;
        const typeAffichage = selectType.value;
        const categorieFiltre = document.getElementById('selectCategorie').value;

        //construit la chaîne de paramètres de base pour la requête
        let parametresRequete =
            `modeAffichage=${modeAffichage}&type=${typeAffichage}&categorie=${encodeURIComponent(categorieFiltre)}`;

        //ajoute les paramètres selon le mode d'affichage choisi
        if (modeAffichage === 'annee') {
            //récupère l'année sélectionnée et l'ajoute aux paramètres
            const anneeSelectionnee = document.getElementById('selectAnnee').value;
            parametresRequete += `&annee=${anneeSelectionnee}`;
        } else {
            //récupère les dates de début et de fin de la période
            const dateDebut = document.getElementById('dateDebut').value;
            const dateFin = document.getElementById('dateFin').value;
            //ne lance pas la requête si les deux dates ne sont pas renseignées
            if (!dateDebut || !dateFin) return;
            parametresRequete += `&debut=${dateDebut}&fin=${dateFin}`;
        }

        //affiche l'indicateur de chargement
        document.getElementById('loadingOverlay').dataset.visible = '';

        //lance la requête fetch vers le script PHP de données
        fetch(`donneStatsClient.php?${parametresRequete}`)
            .then(reponse => reponse.json())
            .then(donnees => {
                //arrête le traitement et affiche l'erreur si le serveur en renvoie une
                if (donnees.error) {
                    console.error(donnees.error);
                    return;
                }

                //met à jour le titre et les données du graphique 1 (évolution mensuelle)
                majLabelGraph1(typeAffichage);
                graph1.data.datasets[0].data = donnees.graph1.data;
                graph1.data.datasets[0].label = obtenirLibelleType(typeAffichage);
                graph1.update();

                //met à jour les labels et les données du graphique 2 (top produits)
                graph2.data.labels = donnees.graph2.labels;
                graph2.data.datasets[0].data = donnees.graph2.data;
                graph2.data.datasets[0].label = obtenirLibelleType(typeAffichage);
                document.getElementById('titreGraph2').textContent =
                    `Top produits – ${obtenirLibelleType(typeAffichage)}`;
                graph2.update();

                //met à jour les labels et les données du graphique 3 (répartition catégories)
                graph3.data.labels = donnees.graph3.labels;
                graph3.data.datasets[0].data = donnees.graph3.data;
                graph3.update();

                //met à jour les indicateurs KPI affichés sur la page
                const indicateursKpi = donnees.kpi;

                //formate et affiche le montant total dépensé en euros
                document.getElementById('kpiDepense').textContent =
                    indicateursKpi.totalDepense.toLocaleString('fr-FR', {
                        style: 'currency',
                        currency: 'EUR'
                    });

                //affiche le nombre total de commandes passées
                document.getElementById('kpiCommandes').textContent = indicateursKpi.totalCommandes;

                //affiche le nombre total d'articles achetés
                document.getElementById('kpiArticles').textContent = indicateursKpi.totalArticles;

                //formate et affiche le panier moyen en euros
                document.getElementById('kpiMoyenne').textContent =
                    indicateursKpi.moyenneCommande.toLocaleString('fr-FR', {
                        style: 'currency',
                        currency: 'EUR'
                    });

                //affiche le nom du produit le plus commandé s'il existe
                document.getElementById('kpiTopProduit').textContent =
                    indicateursKpi.topProduit ? `Article le + commandé : ${indicateursKpi.topProduit}` : '';
            })
            .catch(erreur => console.error('Erreur stats client :', erreur))
            .finally(() => {
                //masque l'indicateur de chargement quoi qu'il arrive
                document.getElementById('loadingOverlay').removeAttribute('data-visible');
            });
    }

    // ============================================
    // FONCTIONS UTILITAIRES
    // ============================================

    //retourne le libellé lisible correspondant au type d'affichage
    function obtenirLibelleType(type) {
        return {
            montant: 'Montant (€)',
            nbArticle: "Nb d'articles",
            nbCommande: 'Nb de commandes'
        } [type] ?? type;
    }

    //met à jour le titre du graphique 1 selon le type d'affichage sélectionné
    function majLabelGraph1(type) {
        const titresParType = {
            montant: 'Montant mensuel (€)',
            nbArticle: 'Articles commandés par mois',
            nbCommande: 'Commandes par mois',
        };
        document.getElementById('titreGraph1').textContent = titresParType[type] ?? '';
    }

    //lance le chargement initial des statistiques au chargement complet de la page
    window.addEventListener('load', chargerStats);
    </script>
</body>

</html>