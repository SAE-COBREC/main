<?php
//démarrer la session pour récupérer les informations du client
session_start();

//inclure le fichier de configuration pour la connexion à la base de données
include __DIR__ . '/selectBDD.php';

//inclure les fonctions utilitaires
include __DIR__ . '/pages/fonctions.php';

//récupérer la connexion PDO depuis le fichier de configuration
$connexionBaseDeDonnees = $pdo;

//définir le schéma de la base de données à utiliser
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

//récupérer l'ID client si connecté
$idClient = $_SESSION['idClient'] ?? null;

if ($idClient === null) {
    //si l'utilisateur n'est pas connecté, on utilise un panier temporaire en SESSION
    if (!isset($_SESSION['panierTemp'])) {
        $_SESSION['panierTemp'] = array();
    }
    $panier = null; //pas de panier en BDD
} else {
    //sinon on récupère l'id de son panier courant (celui qui est en train d'être rempli)
    $sqlPanierClient = "
        SELECT id_panier
        FROM _panier_commande
        WHERE timestamp_commande IS NULL
        AND id_client = :idClient
    ";
    $stmtPanier = $connexionBaseDeDonnees->prepare($sqlPanierClient);
    $stmtPanier->execute([":idClient" => $idClient]);
    $panier = $stmtPanier->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $idPanier = (int) $panier['id_panier'];
    } else {
        $sqlCreatePanier = "
            INSERT INTO _panier_commande (id_client, timestamp_commande)
            VALUES (:idClient, NULL)
            RETURNING id_panier
        ";
        $stmtCreate = $connexionBaseDeDonnees->prepare($sqlCreatePanier);
        $stmtCreate->execute([":idClient" => $idClient]);
        $idPanier = (int) $stmtCreate->fetchColumn();
    }

    $_SESSION["panierEnCours"] = $idPanier;

    transfererPanierTempVersBDD($connexionBaseDeDonnees, $idPanier);
}

//gérer l'ajout au panier via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    header('Content-Type: application/json');

    $idProduit = $_POST['idProduit'] ?? null;
    $quantite = $_POST['quantite'] ?? 1;
    $idClient = $_SESSION['idClient'] ?? null;

    if (!$idProduit) {
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
        exit;
    }

    //envelopper l'appel dans un try-catch
    try {
        if ($idClient === null) {
            //utilisateur non connecté : utiliser le panier temporaire en SESSION
            $resultat = ajouterArticleSession($connexionBaseDeDonnees, $idProduit, $quantite);
        } else {
            //utilisateur connecté : utiliser le panier en BDD
            $idPanier = $_SESSION['panierEnCours'] ?? null;
            if (!$idPanier) {
                echo json_encode(['success' => false, 'message' => 'Aucun panier en cours pour ce client']);
                exit;
            }
            $resultat = ajouterArticleBDD($connexionBaseDeDonnees, $idProduit, $idPanier, $quantite);
        }
    } catch (Exception $e) {
        $resultat = ['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()];
    }

    echo json_encode($resultat);
    exit;
}

//fonction pour filtrer les produits selon les critères choisis
function filtrerProduits($listeProduits, $filtres)
{
    $produits_filtres = [];
    foreach ($listeProduits as $produitCourant) {
        if (($produitCourant['p_prix'] ?? 0) > $filtres['prixMaximum'])
            continue;
        if ($filtres['categorieFiltre'] !== 'all') {
            $categoriesProduit = explode(', ', $produitCourant['categories'] ?? '');
            if (!in_array($filtres['categorieFiltre'], $categoriesProduit))
                continue;
        }
        if ($filtres['enStockSeulement'] && ($produitCourant['p_stock'] ?? 0) <= 0)
            continue;
        if (($produitCourant['note_moyenne'] ?? 0) < $filtres['noteMinimum'])
            continue;
        $produits_filtres[] = $produitCourant;
    }
    return $produits_filtres;
}

//fonction pour trier les produits selon le critère choisi
function trierProduits($listeProduits, $tri_par)
{
    switch ($tri_par) {
        case 'meilleures_ventes':
            usort($listeProduits, function ($a, $b) {
                return ($b['p_nb_ventes'] ?? 0) - ($a['p_nb_ventes'] ?? 0); });
            break;
        case 'prix_croissant':
            usort($listeProduits, function ($a, $b) {
                return ($a['p_prix'] ?? 0) - ($b['p_prix'] ?? 0); });
            break;
        case 'prix_decroissant':
            usort($listeProduits, function ($a, $b) {
                return ($b['p_prix'] ?? 0) - ($a['p_prix'] ?? 0); });
            break;
        case 'note':
            usort($listeProduits, function ($a, $b) {
                $noteA = $a['note_moyenne'] ?? 0;
                $noteB = $b['note_moyenne'] ?? 0;
                return $noteB - $noteA;
            });
            break;
    }
    return $listeProduits;
}

//fonction pour préparer les catégories pour l'affichage
function preparercategories_affichage($listeCategories)
{
    $categories_affichage = [];
    $total_produits = 0;
    foreach ($listeCategories as $nomCategorie => $compte) {
        $categories_affichage[] = [
            'category' => $nomCategorie,
            'count' => $compte
        ];
    }
    array_unshift($categories_affichage, [
        'category' => 'all',
        'count' => $total_produits
    ]);
    return $categories_affichage;
}

//chargement des données depuis la base de données
$donnees = chargerProduitsBDD($connexionBaseDeDonnees);
$listeProduits = $donnees['produits'];
$listeCategories = $donnees['categories'];
$tousLesProduits = count($listeProduits);
$prixMaximumDynamique = getPrixMaximum($connexionBaseDeDonnees);

//récupère les valeurs des filtres depuis le formulaire
$categorieFiltre = $_POST['category'] ?? 'all';
$noteMinimum = $_POST['note'] ?? 0;
$prixMaximum = $_POST['price'] ?? $prixMaximumDynamique;
$enStockSeulement = isset($_POST['in_stock']);
$tri_par = $_POST['sort'] ?? 'meilleures_ventes';

$filtres = [
    'categorieFiltre' => $categorieFiltre,
    'noteMinimum' => $noteMinimum,
    'prixMaximum' => $prixMaximum,
    'enStockSeulement' => $enStockSeulement
];

//application des filtres et du tri
$produits_filtres = filtrerProduits($listeProduits, $filtres);
$listeProduits = trierProduits($produits_filtres, $tri_par);
$categories_affichage = preparercategories_affichage($listeCategories);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="icon" type="image/png" href="/img/favicon.svg">
    <link rel="stylesheet" href="/styles/Index/style.css">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
</head>

<body>
    <?php
    include __DIR__ . '/partials/header.php';
    ?>

    <div class="container">
        <aside style="background-image: url(https://media.licdn.com/dms/image/v2/D4E03AQFHzl7e4f9AOA/profile-displayphoto-scale_200_200/B4EZnsAwbtJ0AY-/0/1760601248177?e=1765411200&v=beta&t=BqJE_NgYrU3tcx8zDv-tj7DN4JVLkJER26Xy3L7RDJI);">
            <section>
                <h3>lorem ipsum</h3>
            </section>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
            <br>
           
        </aside>

        <main>
            <div>
                <?php if (empty($listeProduits)): ?>
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <?php foreach ($listeProduits as $produitCourant): ?>
                        <?php
                        //détermine si le produit est en rupture de stock
                        $estEnRupture = $produitCourant['p_stock'] <= 0;
                        //vérifie si le produit a une remise
                        $possedePourcentageRemise = !empty($produitCourant['pourcentage_reduction']) && $produitCourant['pourcentage_reduction'] > 0;
                        //calcule le prix final (avec remise si applicable)
                        $prixApresRemise = $possedePourcentageRemise
                            ? $produitCourant['p_prix'] * (1 - $produitCourant['pourcentage_reduction'] / 100)
                            : $produitCourant['p_prix'];
                        //arrondit la note moyenne
                        $noteArrondie = $produitCourant['note_moyenne'] ? round($produitCourant['note_moyenne']) : 0;
                        ?>
                        <article class="<?= $estEnRupture ? 'produit-rupture' : '' ?>"
                            onclick="window.location.href='/pages/produit/index.php?id=<?= $produitCourant['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= str_replace("html/img/photo", "/img/photo", htmlspecialchars($produitCourant['image_url'] ?? '/img/default-product.jpg')) ?>"
                                        alt="<?= htmlspecialchars($produitCourant['p_nom']) ?>"
                                        class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                                </div>
                                <?php if ($possedePourcentageRemise): ?>
                                    <span class="badge-reduction">-<?= round($produitCourant['pourcentage_reduction']) ?>%</span>
                                <?php endif; ?>
                                <?php if ($estEnRupture): ?>
                                    <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($produitCourant['p_nom']) ?></h3>
                                <div>
                                    <span><?= str_repeat('<img src="/img/svg/star-yellow-full.svg" alt="★" width="16" style="margin-right:3px;">', $noteArrondie) . str_repeat('<img src="/img/svg/star-yellow-empty.svg" alt="☆" width="16">', 5 - $noteArrondie) ?></span>
                                    <span>(<?= $produitCourant['nombre_avis'] ?>)</span>
                                </div>
                                <div>
                                    <span>
                                        <?php if ($possedePourcentageRemise): ?>
                                            <?= number_format($produitCourant['p_prix'], 2, ',', ' ') ?>€
                                        <?php endif; ?>
                                    </span>
                                    <span><?= number_format($prixApresRemise, 2, ',', ' ') ?>€</span>
                                </div>
                                <button <?= $estEnRupture ? 'disabled' : '' ?>
                                    onclick="event.stopPropagation(); ajouterAuPanier(<?= $produitCourant['id_produit'] ?>)">
                                    <?= $estEnRupture ? 'Indisponible' : '<img src="/img/svg/panier.svg" alt="Panier" class="panier-icon"> Ajouter au panier' ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
    include __DIR__ . '/partials/footer.html';
    include __DIR__ . '/partials/toast.html';
    include __DIR__ . '/partials/modal.html';
    ?>

    <script src="/js/notifications.js"></script>
    <script>
        //fonction pour définir la catégorie et soumettre le formulaire
        function definirCategorie(categorie) {
            document.getElementById('champCategorie').value = categorie;
            document.getElementById('filterForm').submit();
        }

        //fonction pour définir la note minimum et soumettre le formulaire
        function definirNote(note) {
            document.getElementById('champNote').value = note;
            document.getElementById('filterForm').submit();
        }

        //fonction pour mettre à jour l'affichage du prix maximum
        function mettreAJourAffichagePrix(valeur) {
            document.getElementById('affichagePrixMax').textContent = valeur + '€';
        }

        //fonction pour ajouter au panier avec requête AJAX vers la base de données
        function ajouterAuPanier(idProduit) {
            //créer les données du formulaire
            const formData = new FormData();
            formData.append('action', 'ajouter_panier');
            formData.append('idProduit', idProduit);
            formData.append('quantite', 1);

            //envoyer la requête AJAX
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (window.notify) {
                            notify(data.message, 'success');
                        } else {
                            alert('✓ ' + data.message);
                        }
                    } else {
                        if (window.notify) {
                            notify(data.message, 'error');
                        } else {
                            alert('✗ ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    if (window.notify) {
                        notify('Erreur lors de l\'ajout au panier', 'error');
                    } else {
                        alert('Erreur lors de l\'ajout au panier');
                    }
                });
        }

        //fonction pour réinitialiser tous les filtres
        function reinitialiserFiltres() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            document.body.appendChild(form);
            form.submit();
        }

        //fonction pour activer l'édition manuelle du prix maximum
        function activerEditionPrix() {
            const affichagePrix = document.getElementById('affichagePrixMax');
            const prixActuel = affichagePrix.textContent.replace('€', '');
            const prixMaxDynamique = <?= $prixMaximumDynamique ?>;

            //remplace l'affichage par un champ de saisie
            const inputPrix = document.createElement('input');
            inputPrix.type = 'number';
            inputPrix.value = prixActuel;
            inputPrix.min = 0;
            inputPrix.max = prixMaxDynamique;
            inputPrix.style.width = '60px';

            affichagePrix.replaceWith(inputPrix);
            inputPrix.focus();
            inputPrix.select();

            //gère la sauvegarde quand on quitte le champ
            inputPrix.addEventListener('blur', sauvegarderPrix);
            //gère la sauvegarde quand on appuie sur Entrée
            inputPrix.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    sauvegarderPrix();
                }
            });

            //fonction pour sauvegarder la nouvelle valeur du prix
            function sauvegarderPrix() {
                const nouveauPrix = parseInt(inputPrix.value) || 0;
                //s'assure que le prix est dans les limites autorisées
                const prixValide = Math.min(Math.max(nouveauPrix, 0), prixMaxDynamique);

                document.querySelector('input[name="price"]').value = prixValide;

                //recrée l'élément span d'affichage
                const nouveauSpan = document.createElement('span');
                nouveauSpan.id = 'affichagePrixMax';
                nouveauSpan.textContent = prixValide + '€';
                nouveauSpan.ondblclick = activerEditionPrix;

                inputPrix.replaceWith(nouveauSpan);

                //soumet le formulaire pour appliquer le nouveau filtre
                document.getElementById('filterForm').submit();
            }
        }

        //initialisation quand la page est chargée
        document.addEventListener('DOMContentLoaded', function () {
            const aside = document.querySelector('aside');

            if (aside) {
                aside.addEventListener('click', function () {
                    this.classList.toggle('open');
                });
            }
        });
    </script>
</body>

</html>