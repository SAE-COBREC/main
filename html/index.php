<?php
session_start();

include __DIR__ . '/selectBDD.php';

$pdo->exec("SET search_path TO cobrec1");

//fonction pour charger tous les produits depuis la base de données
function chargerProduitsBDD($pdo)
{
    $produits = [];
    $categories = [];

    try {
        //requête SQL pour récupérer tous les produits avec leurs informations
        $sql = "
        SELECT 
            p.id_produit,
            p.p_nom,
            p.p_description,
            p.p_prix,
            p.p_stock,
            p.p_note as note_moyenne,
            p.p_nb_ventes,
            p.p_statut,
            COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction,
            COALESCE(avis.nombre_avis, 0) as nombre_avis,
            (SELECT STRING_AGG(cp.nom_categorie, ', ') 
                FROM _fait_partie_de fpd 
                JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
                WHERE fpd.id_produit = p.id_produit) as categories,
            (SELECT i.i_lien 
                FROM _represente_produit rp 
                JOIN _image i ON rp.id_image = i.id_image
                WHERE rp.id_produit = p.id_produit 
                LIMIT 1) as image_url
        FROM _produit p
        LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
        LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction 
        LEFT JOIN (
            SELECT id_produit, COUNT(*) as nombre_avis 
            FROM _avis 
            GROUP BY id_produit
        ) avis ON p.id_produit = avis.id_produit
    ";

        $stmt = $pdo->query($sql);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        //requête pour compter les produits par catégorie
        $sqlCategories = "
        SELECT cp.nom_categorie as category, 
                COUNT(DISTINCT p.id_produit) as count
        FROM _produit p
        JOIN _fait_partie_de fpd ON p.id_produit = fpd.id_produit
        JOIN _categorie_produit cp ON fpd.id_categorie = cp.id_categorie
        GROUP BY cp.nom_categorie
    ";

        $stmtCategories = $pdo->query($sqlCategories);
        $categoriesResult = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categoriesResult as $cat) {
            $categories[$cat['category']] = $cat['count'];
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors du chargement des produits : " . $e->getMessage() . "</p>";
    }

    return ['produits' => $produits, 'categories' => $categories];
}

//fonction pour ajouter un article au panier dans la BDD
function ajouterArticleBDD($pdo, $idProduit, $idPanier, $quantite = 1)
{
    try {
        //récupérer les informations du produit (prix, TVA, frais de port, remise)
        $sqlProduit = "
            SELECT 
                p.p_prix, 
                p.p_frais_de_port, 
                p.p_stock,
                COALESCE(t.montant_tva, 0) as tva,
                COALESCE(r.reduction_pourcentage, 0) as pourcentage_reduction
            FROM _produit p
            LEFT JOIN _tva t ON p.id_tva = t.id_tva
            LEFT JOIN _en_reduction er ON p.id_produit = er.id_produit
            LEFT JOIN _reduction r ON er.id_reduction = r.id_reduction
            WHERE p.id_produit = :idProduit
        ";

        $stmtProduit = $pdo->prepare($sqlProduit);
        $stmtProduit->execute([':idProduit' => $idProduit]);
        $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            return ['success' => false, 'message' => 'Produit introuvable'];
        }

        // normaliser la quantité demandée
        $quantite = (int) $quantite;
        if ($quantite < 1) {
            $quantite = 1;
        }

        //calculer le prix avec remise
        $prixUnitaire = $produit['p_prix'];
        $remiseUnitaire = ($produit['pourcentage_reduction'] / 100) * $prixUnitaire;
        $fraisDePort = $produit['p_frais_de_port'];
        $tva = $produit['tva'];
        $stock = (int) ($produit['p_stock'] ?? 0);

        //vérifier si l'article existe déjà dans le panier
        $sqlCheck = "SELECT quantite FROM _contient WHERE id_produit = :idProduit AND id_panier = :idPanier";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':idProduit' => $idProduit,
            ':idPanier' => $idPanier
        ]);

        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $quantiteExistante = $existe ? (int) $existe['quantite'] : 0;
        $disponible = max(0, $stock - $quantiteExistante);

        if ($disponible <= 0) {
            return ['success' => false, 'message' => 'Stock insuffisant: quantité maximale déjà atteinte dans votre panier'];
        }

        // quantité réellement ajoutée (ne dépasse pas le disponible)
        $aAjouter = min($quantite, $disponible);

        if ($existe) {
            //si l'article existe déjà, augmenter la quantité
            $sqlUpdate = "UPDATE _contient SET quantite = quantite + :quantite WHERE id_produit = :idProduit AND id_panier = :idPanier";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':quantite' => $aAjouter,
                ':idProduit' => $idProduit,
                ':idPanier' => $idPanier
            ]);
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Quantité mise à jour dans le panier'];
        } else {
            //sinon, insérer un nouvel article avec toutes les informations
            $sqlInsert = "
                INSERT INTO _contient 
                (id_produit, id_panier, quantite, prix_unitaire, remise_unitaire, frais_de_port, tva) 
                VALUES (:idProduit, :idPanier, :quantite, :prixUnitaire, :remiseUnitaire, :fraisDePort, :tva)
            ";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':idProduit' => $idProduit,
                ':idPanier' => $idPanier,
                ':quantite' => $aAjouter,
                ':prixUnitaire' => $prixUnitaire,
                ':remiseUnitaire' => $remiseUnitaire,
                ':fraisDePort' => $fraisDePort,
                ':tva' => $tva
            ]);
            if ($aAjouter < $quantite) {
                return ['success' => true, 'message' => 'Seuls ' . $aAjouter . ' article(s) ont pu être ajouté(s) (stock limité).'];
            }
            return ['success' => true, 'message' => 'Article ajouté au panier'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

$idClient = (int)$_SESSION['id'];

//gérer l'ajout au panier via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_panier') {
    header('Content-Type: application/json');

    $idProduit = $_POST['idProduit'] ?? null;
    $quantite = $_POST['quantite'] ?? 1;
    $idPanier = $idClient; //utilise l'ID du panier actuel 

    if ($idProduit) {
        $resultat = ajouterArticleBDD($pdo, $idProduit, $idPanier, $quantite);
        echo json_encode($resultat);
    } else {
        echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
    }
    exit;
}

//fonction pour récupérer le prix maximum parmi tous les produits
function getPrixMaximum($pdo)
{
    try {
        $sql = "SELECT MAX(p_prix) AS prix_maximum 
            FROM _produit";

        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['prix_maximum'] ? ceil($result['prix_maximum'] / 100) * 100 : 3000;
    } catch (Exception $e) {
        return 3000;
    }
}

//fonction pour filtrer les produits selon les critères choisis
function filtrerProduits($produits, $filtres)
{
    $produits_filtres = [];

    foreach ($produits as $produit) {
        if (($produit['p_prix'] ?? 0) > $filtres['prixMaximum']) {
            continue;
        }

        if ($filtres['categorieFiltre'] !== 'all') {
            $categoriesProduit = explode(', ', $produit['categories'] ?? '');
            if (!in_array($filtres['categorieFiltre'], $categoriesProduit)) {
                continue;
            }
        }

        if ($filtres['enStockSeulement'] && ($produit['p_stock'] ?? 0) <= 0) {
            continue;
        }

        if (($produit['note_moyenne'] ?? 0) < $filtres['noteMinimum']) {
            continue;
        }

        $produits_filtres[] = $produit;
    }

    return $produits_filtres;
}

//fonction pour trier les produits selon le critère choisi
function trierProduits($produits, $tri_par)
{
    switch ($tri_par) {
        case 'meilleures_ventes':
            usort($produits, function ($a, $b) {
                return ($b['p_nb_ventes'] ?? 0) - ($a['p_nb_ventes'] ?? 0);
            });
            break;
        case 'prix_croissant':
            usort($produits, function ($a, $b) {
                return ($a['p_prix'] ?? 0) - ($b['p_prix'] ?? 0);
            });
            break;
        case 'prix_decroissant':
            usort($produits, function ($a, $b) {
                return ($b['p_prix'] ?? 0) - ($a['p_prix'] ?? 0);
            });
            break;
        case 'note':
            usort($produits, function ($a, $b) {
                $noteA = $a['note_moyenne'] ?? 0;
                $noteB = $b['note_moyenne'] ?? 0;
                return $noteB - $noteA;
            });
            break;
    }

    return $produits;
}

//fonction pour préparer les catégories pour l'affichage
function preparercategories_affichage($categories)
{
    $categories_affichage = [];
    $total_produits = 0;

    foreach ($categories as $nomCategorie => $compte) {
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
$donnees = chargerProduitsBDD($pdo);
$produits = $donnees['produits'];
$categories = $donnees['categories'];

$tousLesProduits = count($produits);

$prixMaximumDynamique = getPrixMaximum($pdo);

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
$produits_filtres = filtrerProduits($produits, $filtres);
$produits = trierProduits($produits_filtres, $tri_par);
$categories_affichage = preparercategories_affichage($categories);

$id_panier = 8;

?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - E-commerce</title>
    <link rel="stylesheet" href="/styles/Index/style.css">
    <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
</head>

<body>
    <?php
    include __DIR__ . '/partials/header.html';
    ?>

    <div class="container">
        <aside>
            <form method="POST" action="" id="filterForm">
                <div>
                    <span>Tri par :</span>
                    <select name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="meilleures_ventes" <?= $tri_par === 'meilleures_ventes' ? 'selected' : '' ?>>
                            Meilleures ventes
                        </option>
                        <option value="prix_croissant" <?= $tri_par === 'prix_croissant' ? 'selected' : '' ?>>Prix
                            croissant</option>
                        <option value="prix_decroissant" <?= $tri_par === 'prix_decroissant' ? 'selected' : '' ?>>Prix
                            décroissant
                        </option>
                        <option value="note" <?= $tri_par === 'note' ? 'selected' : '' ?>>Mieux notés</option>
                    </select>
                </div>

                <div>
                    <h3>Filtres</h3>
                    <button type="button" onclick="reinitialiserFiltres()">Effacer</button>
                </div>

                <section>
                    <h4>Catégories</h4>
                    <div onclick="definirCategorie('all')">
                        <span>Tous les produits</span>
                        <span><?= $tousLesProduits ?></span>
                    </div>
                    <?php foreach ($categories_affichage as $categorie): ?>
                        <?php if ($categorie['category'] !== 'all'): ?>
                            <div onclick="definirCategorie('<?= htmlspecialchars($categorie['category']) ?>')">
                                <span><?= htmlspecialchars($categorie['category']) ?></span>
                                <span><?= $categorie['count'] ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <section>
                    <h4>Prix</h4>
                    <div>
                        <input type="range" name="price" min="0" max="<?= $prixMaximumDynamique ?>"
                            value="<?= $prixMaximum ?>" oninput="mettreAJourAffichagePrix(this.value)"
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div>
                        <span>0€</span>
                        <span id="affichagePrixMax" ondblclick="activerEditionPrix()"><?= $prixMaximum ?>€</span>
                    </div>
                </section>

                <section>
                    <h4>Note minimum</h4>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div onclick="definirNote(<?= $i ?>)">
                            <span><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?></span>
                            <span><?= $i ?> et plus</span>
                        </div>
                    <?php endfor; ?>
                </section>

                <section>
                    <h4>Disponibilité</h4>
                    <label>
                        <input type="checkbox" name="in_stock" <?= $enStockSeulement ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <span>En stock uniquement</span>
                    </label>
                </section>

                <input type="hidden" name="category" id="champCategorie"
                    value="<?= htmlspecialchars($categorieFiltre) ?>">
                <input type="hidden" name="note" id="champNote" value="<?= $noteMinimum ?>">
            </form>
        </aside>

        <main>
            <div>
                <?php if (empty($produits)): ?>
                    <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <?php else: ?>
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        //détermine si le produit est en rupture de stock
                        $estEnRupture = $produit['p_stock'] <= 0;
                        //vérifie si le produit a une remise
                        $aUneRemise = !empty($produit['pourcentage_reduction']) && $produit['pourcentage_reduction'] > 0;
                        //calcule le prix final (avec remise si applicable)
                        $prixFinal = $aUneRemise
                            ? $produit['p_prix'] * (1 - $produit['pourcentage_reduction'] / 100)
                            : $produit['p_prix'];
                        //arrondit la note moyenne
                        $note = $produit['note_moyenne'] ? round($produit['note_moyenne']) : 0;
                        ?>
                        <article class="<?= $estEnRupture ? 'produit-rupture' : '' ?>"
                            onclick="window.location.href='/pages/produit/index.php?id=<?= $produit['id_produit'] ?>'">
                            <div>
                                <div>
                                    <img src="<?= htmlspecialchars($produit['image_url'] ?? '/img/default-product.jpg') ?>"
                                        alt="<?= htmlspecialchars($produit['p_nom']) ?>"
                                        class="<?= $estEnRupture ? 'image-rupture' : '' ?>">
                                </div>
                                <?php if ($aUneRemise): ?>
                                    <span class="badge-reduction">-<?= round($produit['pourcentage_reduction']) ?>%</span>
                                <?php endif; ?>
                                <?php if ($estEnRupture): ?>
                                    <div class="rupture-stock">Rupture de stock</div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($produit['p_nom']) ?></h3>
                                <div>
                                    <span><?= str_repeat('★', $note) . str_repeat('☆', 5 - $note) ?></span>
                                    <span>(<?= $produit['nombre_avis'] ?>)</span>
                                </div>
                                <div>
                                    <span>
                                        <?php if ($aUneRemise): ?>
                                            <?= number_format($produit['p_prix'], 2, ',', ' ') ?>€
                                        <?php endif; ?>
                                    </span>
                                    <span><?= number_format($prixFinal, 2, ',', ' ') ?>€</span>
                                </div>
                                <button <?= $estEnRupture ? 'disabled' : '' ?>
                                    onclick="event.stopPropagation(); ajouterAuPanier(<?= $produit['id_produit'] ?>)">
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
    ?>

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
                        alert('✓ ' + data.message);
                        //optionnel : mettre à jour le compteur du panier
                        //mettreAJourCompteurPanier();
                    } else {
                        alert('✗ ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de l\'ajout au panier');
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